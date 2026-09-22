<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Http;
use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

class AmazonController extends Controller
{
    private function getAccessToken(): string
    {
        $response = Http::asForm()->post(
            'https://api.amazon.com/auth/o2/token',
            [
                'grant_type' => 'refresh_token',
                'refresh_token' => config('services.amazon.refresh_token'),
                'client_id' => config('services.amazon.client_id'),
                'client_secret' => config('services.amazon.client_secret'),
            ]
        );

        if ($response->failed()) {
            throw new \Exception( 'Amazon LWA token error [' . $response->status() . ']: ' . $response->body() );
        }

        $accessToken = $response->json('access_token');

        if (!$accessToken) { 
            throw new \Exception( 'Amazon did not return an access_token: ' . $response->body() ); 
        }

        return $accessToken;
    }

    public function testToken(Request $request)
    {
        try { 
            $accessToken = $this->getAccessToken();
            $baseEndpoint = rtrim(config('services.amazon.endpoint'), '/');
       
            $url = $baseEndpoint . '/sellers/v1/marketplaceParticipations';

            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'x-amz-date'         => gmdate('Ymd\THis\Z'),
                'Accept'             => 'application/json',
                'User-Agent'         => 'MyAmazonApp/1.0 (Language=PHP)',
            ])->get($url);

            if ($response->successful()) {
                return response()->json([
                    'valid'       => true,
                    'message'     => 'Token is working perfectly.',
                    'status'      => $response->status(),
                    'marketplaces'=> $response->json('payload'), // Lists authorized countries
                    'access_token' => $accessToken,
                ], 200);
            }

            return response()->json([
                'valid'   => false,
                'message' => 'Token rejected by Amazon.',
                'status'  => $response->status(),
                'error'   => $response->json() ?? $response->body()
            ], $response->status());

        } catch (\Throwable $e) { 
            return response()->json([ 'success' => false, 'message' => $e->getMessage(), ], 500); 
        }
    }

    public function getCatalogItem(Request $request)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $baseEndpoint = rtrim(config('services.amazon.endpoint'),'/');

            $marketplaceId = config('services.amazon.marketplace_id');

            $url = $baseEndpoint . '/catalog/2022-04-01/items';

            $marketplaceId   = $request->query('marketplaceIds', config('services.amazon.marketplace_id'));
            $identifiers     = $request->query('identifiers');
            $identifiersType = $request->query('identifiersType', 'ASIN');
            $includedData    = $request->query('includedData', 'summaries,attributes,dimensions,images,productTypes');

            if (!$identifiers) {
                return response()->json([
                    'success' => false, 
                    'message' => 'The "identifiers" query parameter (ASIN) is required for production lookups.'
                ], 400);
            }
            $queryParams = [
                'marketplaceIds'  => (string) $marketplaceId,
                'identifiers'     => (string) $identifiers,
                'identifiersType' => (string) $identifiersType,
                'includedData'    => (string) $includedData,
            ];

            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Accept'             => 'application/json',
                'User-Agent'         => 'MyAmazonApp/1.0 (Language=PHP)',
            ])->get($url, $queryParams);

            return response()->json([
                'success'     => $response->successful(),
                'status'      => $response->status(),
                'url'         => $url,
                'query'       => $queryParams, 
                'error_type'  => $response->header('x-amzn-ErrorType'),
                'request_id'  => $response->header('x-amzn-RequestId'),
                'body'        => $response->json() ?? $response->body(),
            ], $response->status());

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function createListing(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'product_type' => 'required|string',
            'attributes' => 'nullable|array',
        ]);

        try {
            $accessToken = $this->getAccessToken();

            $sellerId = config('services.amazon.seller_id');
            $marketplaceId = config('services.amazon.marketplace_id');
            $endpoint = rtrim(
                config('services.amazon.endpoint'),
                '/'
            );

            $sku = $validated['sku'];

            $url = $endpoint
                . '/listings/2021-08-01/items/'
                . rawurlencode($sellerId)
                . '/'
                . rawurlencode($sku);

            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->put($url, [
                'productType' => $validated['product_type'],

                'requirements' => 'LISTING',

                'attributes' => $validated['attributes'] ?? [],
            ]);

            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'amazon_response' => $response->json(),
            ], $response->status());
        }catch (\Throwable $e) {
            print_r($e);exit;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}   

