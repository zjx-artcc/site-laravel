<?php

namespace App\Services;

use App\DTOs\VisitingChecklistDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VisitingChecklistService
{
    public function getChecklistItems(string $cid)
    {
        $url = config('app.vatusa_api_url').'/v2/user/'.$cid.'/transfer/checklist';

        try {
            $response = Http::retry(2, 500)->timeout(20)->get($url, [
                'apikey' => config('app.vatusa_api_key'),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch VATUSA visiting checklist for CID '.$cid.': '.$e->getMessage());

            return new VisitingChecklistDTO(null);
        }

        if ($response->status() !== 200) {
            return new VisitingChecklistDTO(null);
        }

        return new VisitingChecklistDTO($response->json());
    }
}
