<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Development;
use App\Models\Lot;
use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $residential = Development::firstOrCreate(
            ['name' => 'Residencial Jardim das Palmeiras'],
            [
                'description' => 'Residential development with full infrastructure.',
                'location' => 'Av. Principal, 1200 - Zona Norte',
                'status' => 'active',
            ]
        );

        $commercial = Development::firstOrCreate(
            ['name' => 'Parque Empresarial Sid360'],
            [
                'description' => 'Commercial development for warehouses and retail.',
                'location' => 'Rod. BR-101, km 42',
                'status' => 'under_construction',
            ]
        );

        $lots = [
            ['development_id' => $residential->id, 'number' => '01', 'block' => 'A', 'area' => 250, 'total_value' => 85000, 'status' => 'available'],
            ['development_id' => $residential->id, 'number' => '02', 'block' => 'A', 'area' => 260, 'total_value' => 88000, 'status' => 'reserved'],
            ['development_id' => $residential->id, 'number' => '03', 'block' => 'A', 'area' => 255, 'total_value' => 87000, 'status' => 'sold'],
            ['development_id' => $commercial->id, 'number' => '10', 'block' => '1', 'area' => 500, 'total_value' => 320000, 'status' => 'available'],
            ['development_id' => $commercial->id, 'number' => '11', 'block' => '1', 'area' => 480, 'total_value' => 305000, 'status' => 'available'],
        ];

        foreach ($lots as $lot) {
            Lot::firstOrCreate(
                [
                    'development_id' => $lot['development_id'],
                    'number' => $lot['number'],
                    'block' => $lot['block'],
                ],
                $lot
            );
        }
    }
}
