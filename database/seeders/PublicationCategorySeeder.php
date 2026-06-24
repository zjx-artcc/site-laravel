<?php

namespace Database\Seeders;

use App\Models\PublicationCategory;
use Illuminate\Database\Seeder;

class PublicationCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Standard Operating Procedures' => 'Facility-wide and position-specific standard operating procedures for vZJX controllers.',
            'Letters of Agreement'          => 'Operational agreements between vZJX and adjacent facilities governing coordination procedures.',
            'Training Materials'            => 'Study guides, training syllabi, and reference materials for controller certification.',
            'Quick Reference Guides'        => 'Quick reference cards and cheat sheets for use during controlling sessions.',
            'Facility Maps & Charts'        => 'Airspace diagrams, sector maps, and facility charts for ZJX ARTCC.',
        ];

        $order = 0;
        foreach ($categories as $title => $description) {
            PublicationCategory::firstOrCreate(
                ['title' => $title],
                [
                    'description'   => $description,
                    'display_order' => $order,
                ],
            );
            $order++;
        }
    }
}
