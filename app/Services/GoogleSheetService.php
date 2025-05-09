<?php

namespace App\Services;

use Google\Client;
use Google\Exception;
use Google\Service\Sheets;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GoogleSheetService
{
    const TEST_SHEET_NAME = 'Sheet1!A:I';
    const NP_SHEET_NAME = 'Matches!A:I';
    private Sheets $service;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $credentials = [
            'type' => config('services.google.type'),
            'project_id' => config('services.google.project_id'),
            'private_key_id' => config('services.google.private_key_id'),
            'private_key' => str_replace('\n', "\n", config('services.google.private_key')),
            'client_email' => config('services.google.client_email'),
            'client_id' => config('services.google.client_id'),
            'auth_uri' => config('services.google.auth_uri'),
            'token_uri' => config('services.google.token_uri'),
            'auth_provider_x509_cert_url' => config('services.google.auth_provider_cert_url'),
            'client_x509_cert_url' => config('services.google.client_cert_url'),
            'universe_domain' => config('services.google.universe_domain')
        ];

        $client = new Client();
        $client->setAuthConfig($credentials);
        $client->addScope(Sheets::SPREADSHEETS);

        $this->service = new Sheets($client);
    }

    public function appendMatches(Collection $matches): bool
    {
        try {
        if ($matches->isEmpty()) {
            Log::info('No matches to append to sheet');
            return true;
        }

            $quarter = ceil(now()->month / 3);
            $values = [];
            $requests = [];

            $values[] = ["Q{$quarter} Match Session"];

            foreach ($matches as $index => $match) {
                $values[] = [
                    $match->member1->name,
                    $match->member1->city ?? 'N/A',
                    $match->member2->name,
                    $match->member2->city ?? 'N/A',
                    $match->member3?->name ?? 'N/A',
                    $match->member3?->city ?? 'N/A'
                ];

                if ($index % 2 === 1) {
                    $requests[] = [
                        'repeatCell' => [
                            'range' => [
                                'startRowIndex' => count($values),
                                'endRowIndex' => count($values) + 1,
                                'startColumnIndex' => 0,
                                'endColumnIndex' => 9
                            ],
                            'cell' => [
                                'userEnteredFormat' => [
                                    'backgroundColor' => [
                                        'red' => 0.85,
                                        'green' => 0.92,
                                        'blue' => 0.99
                                    ]
                                ]
                            ],
                            'fields' => 'userEnteredFormat.backgroundColor'
                        ]
                    ];
                }
            }

            $this->service->spreadsheets_values->append(
                config('services.google.sheets_id'),
                self::NP_SHEET_NAME,
                new Sheets\ValueRange(['values' => $values]),
                ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']
            );

            if (!empty($requests)) {
                $batchUpdateRequest = new Sheets\BatchUpdateSpreadsheetRequest([
                    'requests' => $requests
                ]);

                $this->service->spreadsheets->batchUpdate(
                    config('services.google.sheets_id'),
                    $batchUpdateRequest
                );
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to append matches to sheet', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
