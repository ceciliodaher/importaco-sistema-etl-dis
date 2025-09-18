<?php
require_once '/Users/ceciliodaher/Documents/git/importaco-sistema/sistema/core/parsers/DiXmlParser.php';

try {
    $parser = new DiXmlParser(true);
    $xmlPath = '/Users/ceciliodaher/Documents/git/importaco-sistema/orientacoes/2518173187.xml';
    
    $result = $parser->parseXml($xmlPath);
    
    if (!empty($result['adicoes'])) {
        $adicao = $result['adicoes'][0];
        echo "Código da moeda extraído: '" . $adicao['moeda_codigo'] . "'\n";
        echo "Nome da moeda extraído: '" . $adicao['moeda_nome'] . "'\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}