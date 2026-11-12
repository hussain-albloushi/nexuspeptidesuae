<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The storefront content seeded from the previous brand hotlinked its images
 * and COA files from another company's website (zenvypeptides.com). Replace
 * the images with the site's own photography and drop the borrowed
 * certificate listings — the admin can publish real COAs from Website
 * Content, and the /coa page explains how to request one meanwhile.
 */
return new class extends Migration
{
    private const REPLACED_IMAGES = [
        'https://zenvypeptides.com/wp-content/uploads/2026/09/GLP-3-Peptides.png' => '/site-images/hero-peptides.jpg',
        'https://zenvypeptides.com/wp-content/uploads/2026/09/123.png' => '/site-images/trusted-vial.png',
        'https://zenvypeptides.com/wp-content/uploads/2026/09/321.png' => '/site-images/purity-vials.png',
        'https://zenvypeptides.com/wp-content/uploads/2026/07/569ce316-e32d-42ad-96cd-aa9ed39e5909.png' => '/site-images/about-peptides.jpg',
        'https://zenvypeptides.com/wp-content/uploads/2026/07/b69dec56-63c2-4f91-a619-13e10beaaa89.jpg' => '/site-images/lab-vial.png',
    ];

    private const COA_INTRO = 'Every order ships with a batch-specific Certificate of Analysis verifying purity and testing standards. Current batch certificates are published below as they become available; if the batch you need is not listed yet, contact support and we will provide it.';

    public function up(): void
    {
        foreach (DB::table('contents')->whereIn('key', ['home', 'about', 'coa'])->get() as $row) {
            $data = json_decode((string) $row->data, true);
            if (! is_array($data)) {
                continue;
            }

            $data = $this->replaceImages($data);

            if ($row->key === 'coa') {
                $data['coas'] = [];
                $data['intro'] = self::COA_INTRO;
            }

            DB::table('contents')->where('key', $row->key)->update([
                'data' => json_encode($data),
            ]);
        }
    }

    /**
     * The removed COA rows pointed at another brand's files; restoring them
     * would reintroduce the links this migration exists to remove.
     */
    public function down(): void {}

    private function replaceImages(array $data): array
    {
        array_walk_recursive($data, function (&$value): void {
            if (is_string($value) && isset(self::REPLACED_IMAGES[$value])) {
                $value = self::REPLACED_IMAGES[$value];
            }
        });

        return $data;
    }
};
