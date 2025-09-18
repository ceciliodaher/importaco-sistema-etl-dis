<?php
require_once '/Users/ceciliodaher/Documents/git/importaco-sistema/sistema/core/parsers/DiXmlParser.php';

try {
    $parser = new DiXmlParser(true);
    $xmlPath = '/Users/ceciliodaher/Documents/git/importaco-sistema/orientacoes/2518173187.xml';
    
    $result = $parser->parseXml($xmlPath);
    
    if (!empty($result['adicoes'])) {
        $adicao = $result['adicoes'][0];
        echo "Impostos extraídos:\n";
        
        foreach ($adicao['impostos'] as $tipo => $imposto) {
            echo "$tipo:\n";
            echo "  Alíquota DI: " . ($imposto['aliquota_di'] ?? 'NULL') . "\n";
            echo "  Valor DI: " . ($imposto['valor_di'] ?? 'NULL') . "\n";
            echo "  Base DI: " . ($imposto['base_di'] ?? 'NULL') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}