<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();
$user_id = (int)($_POST['user_id'] ?? 0);

// For now, just return a simple CSV
$products = $db->getThisQuery("
    SELECT 
        p.sku,
        p.name,
        p.category,
        p.stock_quantity as product_stock,
        p.cost_price,
        p.price,
        pv.sku as variant_sku,
        pv.attributes,
        pv.stock_quantity as variant_stock,
        pv.price as variant_price
    FROM products p
    LEFT JOIN product_variations pv ON p.id = pv.product_id
    WHERE p.user_id = ?
    ORDER BY p.name, pv.sku
", [$user_id]);

// Create CSV content
$csv = "SKU,Produit,Type,Stock,Prix d'achat,Prix de vente\n";
foreach ($products as $product) {
    if ($product['variant_sku']) {
        $csv .= sprintf('"%s","%s","%s",%d,%.2f,%.2f' . "\n",
            $product['variant_sku'],
            $product['name'],
            'Variante',
            $product['variant_stock'],
            $product['cost_price'],
            $product['variant_price']
        );
    } else {
        $csv .= sprintf('"%s","%s","%s",%d,%.2f,%.2f' . "\n",
            $product['sku'],
            $product['name'],
            'Produit',
            $product['product_stock'],
            $product['cost_price'],
            $product['price']
        );
    }
}

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inventaire_' . date('Y-m-d') . '.csv"');
echo $csv;