<?php
require_once '/Users/ceciliodaher/Documents/git/importaco-sistema/sistema/core/parsers/DiXmlParser.php';

try {
    $parser = new DiXmlParser(true);
    $xmlPath = '/Users/ceciliodaher/Documents/git/importaco-sistema/orientacoes/2518173187.xml';
    
    echo "Processando XML: $xmlPath\n";
    $result = $parser->parseXml($xmlPath);
    
    echo "DI: " . $result['numero_di'] . "\n";
    echo "Total Adições: " . $result['total_adicoes'] . "\n";
    
    if (!empty($result['adicoes'])) {
        $adicao = $result['adicoes'][0];
        echo "VMLE Moeda: " . ($adicao['valor_vmle_moeda'] ?? 'NULL') . "\n";
        echo "VMLE Reais: " . ($adicao['valor_vmle_reais'] ?? 'NULL') . "\n";
        echo "VMCV Moeda: " . $adicao['valor_vmcv_moeda'] . "\n";
        echo "VMCV Reais: " . $adicao['valor_vmcv_reais'] . "\n";
    }
    
    echo "Salvando no banco...\n";
    $parser->saveToDatabase($result);
    
    echo "SUCESSO: DI " . $result['numero_di'] . " salva com sucesso!\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}