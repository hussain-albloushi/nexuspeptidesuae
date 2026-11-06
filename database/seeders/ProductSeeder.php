<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
class ProductSeeder extends Seeder {
    public function run(): void {
        $cat = Category::where('slug','peptides')->first();
        $items = [
            ['name'=>'Bacteriostatic Water 10ml','slug'=>'bacteriostatic-water-10ml','description'=>'Sterile bacteriostatic water for peptide reconstitution and laboratory use.','price'=>14.95,'compare_at_price'=>19.95,'sku'=>'BAC-10ML','featured_image_url'=>'/catalog-images/bacteriostatic-water-10ml.webp','category_id'=>$cat?$cat->id:null],
            ['name'=>'Epitalon 10mg','slug'=>'epitalon-10mg','description'=>'Synthetic peptide compound researched for cellular health and circadian regulation studies.','price'=>29.95,'compare_at_price'=>39.95,'sku'=>'EPI-10MG','featured_image_url'=>'/catalog-images/epitalon-10mg.webp','category_id'=>$cat?$cat->id:null],
            ['name'=>'Melanotan-2 10mg','slug'=>'melanotan-2-10mg','description'=>'Synthetic analog of alpha-MSH studied for melanogenesis and UV-response research.','price'=>29.95,'compare_at_price'=>39.95,'sku'=>'MEL-10MG','featured_image_url'=>'/catalog-images/melanotan-2-10mg.webp','category_id'=>$cat?$cat->id:null],
            ['name'=>'GHK-CU 50mg','slug'=>'ghk-cu-50mg','description'=>'Copper peptide complex used in tissue repair, wound healing, and anti-aging research.','price'=>29.95,'compare_at_price'=>39.95,'sku'=>'GHK-50MG','featured_image_url'=>'/catalog-images/ghk-cu-50mg.webp','category_id'=>$cat?$cat->id:null],
            ['name'=>'Mots-C 10mg','slug'=>'mots-c-10mg','description'=>'Synthetic peptide fragment researched for mitochondrial function and metabolic studies.','price'=>39.95,'compare_at_price'=>49.95,'sku'=>'MOT-10MG','featured_image_url'=>'/catalog-images/mots-c-10mg.webp','category_id'=>$cat?$cat->id:null],
            ['name'=>'CJC 1595 / Impamorelin 5mg/5mg','slug'=>'cjc-1595-impamorelin-5mg','description'=>'Growth hormone secretagogue analog for metabolic and endurance research.','price'=>39.95,'compare_at_price'=>49.95,'sku'=>'CJC-1595','featured_image_url'=>'/catalog-images/cjc-1595-impamorelin-5mg.webp','category_id'=>$cat?$cat->id:null],
            ['name'=>'AOD-9604 5mg','slug'=>'aod-9604-5mg','description'=>'Fragment of human growth hormone studied for lipid metabolism and adipose tissue research.','price'=>39.95,'compare_at_price'=>49.95,'sku'=>'AOD-5MG','featured_image_url'=>'/catalog-images/aod-9604-5mg.webp','category_id'=>$cat?$cat->id:null],
            ['name'=>'Semax 10mg','slug'=>'semax-10mg','description'=>'Cognitive and neuroprotective peptide compound for brain health research.','price'=>44.95,'compare_at_price'=>59.95,'sku'=>'SEM-10MG','featured_image_url'=>'/catalog-images/semax-10mg.webp','category_id'=>$cat?$cat->id:null],
            ['name'=>'BPC-157 10mg','slug'=>'bpc-157-10mg','description'=>'Gastric protective peptide researched for tissue regeneration and wound healing.','price'=>59.95,'compare_at_price'=>69.95,'sku'=>'BPC-10MG','featured_image_url'=>'/catalog-images/bpc-157-10mg.webp','category_id'=>$cat?$cat->id:null],
            ['name'=>'NAD 500mg','slug'=>'nad-500mg','description'=>'Nicotinamide adenine dinucleotide studied for metabolic health and aging research.','price'=>59.95,'compare_at_price'=>69.95,'sku'=>'NAD-500MG','featured_image_url'=>'/catalog-images/nad-500mg.webp','category_id'=>$cat?$cat->id:null],
        ];
        foreach ($items as $i) Product::firstOrCreate(['slug'=>$i['slug']], $i);
    }
}