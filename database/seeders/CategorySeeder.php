<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Category;
class CategorySeeder extends Seeder {
    public function run(): void {
        $cats = [
            ['name'=>'Peptides','slug'=>'peptides','description'=>'Research-grade peptide compounds for laboratory studies.','is_active'=>true,'image_url'=>'/catalog-images/bacteriostatic-water-10ml.webp'],
            ['name'=>'Nootropics','slug'=>'nootropics','description'=>'Cognitive and neuroprotective research compounds.','is_active'=>true,'image_url'=>'/catalog-images/epitalon-10mg.webp'],
            ['name'=>'Growth & Recovery','slug'=>'growth-recovery','description'=>'Peptides studied for tissue repair, muscle recovery, and metabolic research.','is_active'=>true,'image_url'=>'/catalog-images/melanotan-2-10mg.webp'],
            ['name'=>'Anti-Aging','slug'=>'anti-aging','description'=>'Research peptides focused on cellular health and longevity studies.','is_active'=>true,'image_url'=>'/catalog-images/ghk-cu-50mg.webp'],
        ];
        foreach ($cats as $c) Category::firstOrCreate(['slug'=>$c['slug']], $c);
    }
}