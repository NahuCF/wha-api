<?php

use App\Models\Industry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $industries = [
            ['name' => 'Agriculture'],
            ['name' => 'Forestry'],
            ['name' => 'Fishing'],
            ['name' => 'Mining'],
            ['name' => 'Oil and Gas Extraction'],
            ['name' => 'Automotive'],
            ['name' => 'Aerospace'],
            ['name' => 'Electronics'],
            ['name' => 'Textile and Apparel'],
            ['name' => 'Construction'],
            ['name' => 'Chemicals'],
            ['name' => 'Pharmaceuticals'],
            ['name' => 'Energy Production'],
            ['name' => 'Metalworking and Metallurgy'],
            ['name' => 'Food and Beverage Processing'],
            ['name' => 'Healthcare'],
            ['name' => 'Education'],
            ['name' => 'Retail'],
            ['name' => 'Hospitality and Tourism'],
            ['name' => 'Entertainment and Media'],
            ['name' => 'Financial Services'],
            ['name' => 'Real Estate'],
            ['name' => 'Transportation and Logistics'],
            ['name' => 'Telecommunications'],
            ['name' => 'Professional Services'],
            ['name' => 'IT and Software Services'],
            ['name' => 'Advertising and Marketing'],
            ['name' => 'Research and Development'],
            ['name' => 'Technology and Software Development'],
            ['name' => 'Data Analysis and Services'],
            ['name' => 'Artificial Intelligence'],
            ['name' => 'Biotechnology'],
            ['name' => 'Government and Public Administration'],
            ['name' => 'Nonprofits and NGOs'],
            ['name' => 'Corporate Leadership'],
            ['name' => 'Policy Development'],
            ['name' => 'Renewable Energy'],
            ['name' => 'E-commerce'],
            ['name' => 'Cryptocurrency and Blockchain'],
            ['name' => 'Cybersecurity'],
            ['name' => 'Space Exploration'],
            ['name' => 'Esports and Gaming'],
            ['name' => 'Augmented and Virtual Reality'],
            ['name' => 'Sustainable and Green Technologies'],
            ['name' => 'Autonomous Vehicles'],
            ['name' => 'Wellness and Fitness Tech'],
        ];

        Industry::insert($industries);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
