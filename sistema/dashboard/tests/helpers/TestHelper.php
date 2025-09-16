<?php
/**
 * ================================================================================
 * TEST HELPER - Utilitários Gerais para Testes
 * Funções auxiliares, assertions customizadas e helpers comuns
 * ================================================================================
 */

/**
 * Classe helper principal para testes
 */
class TestHelper
{
    /**
     * Gerar dados fake para DI
     */
    public static function generateDIData(array $overrides = []): array
    {
        $base = [
            'numero_di' => self::generateDINumber(),
            'data_registro' => self::randomDate(),
            'importador_nome' => self::randomCompanyName(),
            'importador_cnpj' => self::generateCNPJ(),
            'valor_total_usd' => mt_rand(10000, 500000),
            'valor_total_brl' => 0, // Será calculado
            'status' => 'concluida'
        ];
        
        $data = array_merge($base, $overrides);
        
        // Calcular BRL se não fornecido
        if ($data['valor_total_brl'] === 0) {
            $data['valor_total_brl'] = $data['valor_total_usd'] * 5.0; // Taxa fixa para testes
        }
        
        return $data;
    }
    
    /**
     * Gerar número de DI válido
     */
    public static function generateDINumber(): string
    {
        $year = date('y');
        $sequential = str_pad(mt_rand(1, 9999999), 7, '0', STR_PAD_LEFT);
        return "{$year}BR{$sequential}";
    }
    
    /**
     * Gerar CNPJ válido (apenas formato, não validação de dígitos)
     */
    public static function generateCNPJ(): string
    {
        $n1 = str_pad(mt_rand(10, 99), 2, '0', STR_PAD_LEFT);
        $n2 = str_pad(mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
        $n3 = str_pad(mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
        $n4 = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $n5 = str_pad(mt_rand(10, 99), 2, '0', STR_PAD_LEFT);
        
        return "{$n1}.{$n2}.{$n3}/{$n4}-{$n5}";
    }
    
    /**
     * Gerar data aleatória
     */
    public static function randomDate(string $start = '2024-01-01', string $end = 'now'): string
    {
        $startTimestamp = strtotime($start);
        $endTimestamp = strtotime($end);
        $randomTimestamp = mt_rand($startTimestamp, $endTimestamp);
        
        return date('Y-m-d', $randomTimestamp);
    }
    
    /**
     * Gerar nome de empresa aleatório
     */
    public static function randomCompanyName(): string
    {
        $prefixes = ['Importadora', 'Comércio', 'Indústria', 'Distribuidora', 'Equipamentos'];
        $names = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Omega', 'TechCorp', 'GlobalTrade', 'Equiplex'];
        $suffixes = ['Ltda', 'S.A.', 'ME', 'EPP', 'EIRELI'];
        
        $prefix = $prefixes[array_rand($prefixes)];
        $name = $names[array_rand($names)];
        $suffix = $suffixes[array_rand($suffixes)];
        
        return "{$prefix} {$name} {$suffix}";
    }
    
    /**
     * Verificar se response é JSON válido
     */
    public static function assertValidJsonResponse(string $response): array
    {
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Response não é JSON válido: ' . json_last_error_msg());
        }
        
        return $decoded;
    }
    
    /**
     * Verificar estrutura de resposta da API
     */
    public static function assertApiResponseStructure(array $response, array $expectedKeys = []): void
    {
        $defaultKeys = ['success', 'data', 'message', 'timestamp'];
        $keys = array_merge($defaultKeys, $expectedKeys);
        
        foreach ($keys as $key) {
            if (!array_key_exists($key, $response)) {
                throw new InvalidArgumentException("Chave obrigatória '{$key}' não encontrada na resposta da API");
            }
        }
    }
    
    /**
     * Verificar timing de performance
     */
    public static function assertPerformanceTiming(float $executionTime, float $maxTime, string $operation = 'Operação'): void
    {
        if ($executionTime > $maxTime) {
            throw new InvalidArgumentException(
                "{$operation} demorou {$executionTime}ms, mas o limite é {$maxTime}ms"
            );
        }
    }
    
    /**
     * Gerar XML de DI para testes
     */
    public static function generateTestXML(array $data = []): string
    {
        $defaults = [
            'numero_di' => self::generateDINumber(),
            'data_registro' => self::randomDate(),
            'importador_nome' => self::randomCompanyName(),
            'importador_cnpj' => self::generateCNPJ(),
            'valor_usd' => mt_rand(10000, 100000),
            'ncm' => '85371000',
            'descricao' => 'Equipamento eletrônico de teste'
        ];
        
        $xmlData = array_merge($defaults, $data);
        
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<declaracaoImportacao>
    <numero>{$xmlData['numero_di']}</numero>
    <dataRegistro>{$xmlData['data_registro']}</dataRegistro>
    <importador>
        <nome>{$xmlData['importador_nome']}</nome>
        <cnpj>{$xmlData['importador_cnpj']}</cnpj>
    </importador>
    <adicoes>
        <adicao numero=\"1\">
            <mercadoria>
                <ncm>{$xmlData['ncm']}</ncm>
                <descricao>{$xmlData['descricao']}</descricao>
                <valor moeda=\"USD\">{$xmlData['valor_usd']}</valor>
                <peso unidade=\"KG\">100.000</peso>
            </mercadoria>
            <impostos>
                <imposto tipo=\"II\">
                    <aliquota>14.00</aliquota>
                    <valor>{$xmlData['valor_usd'] * 0.14}</valor>
                </imposto>
                <imposto tipo=\"IPI\">
                    <aliquota>15.00</aliquota>
                    <valor>{$xmlData['valor_usd'] * 0.15}</valor>
                </imposto>
            </impostos>
        </adicao>
    </adicoes>
</declaracaoImportacao>";
    }
    
    /**
     * Salvar XML temporário para testes
     */
    public static function saveTestXML(string $xml, string $filename = null): string
    {
        if (!$filename) {
            $filename = 'test_di_' . uniqid() . '.xml';
        }
        
        $tempDir = TEST_ROOT . '/temp';
        $filepath = $tempDir . '/' . $filename;
        
        file_put_contents($filepath, $xml);
        
        return $filepath;
    }
    
    /**
     * Limpar arquivos temporários de teste
     */
    public static function cleanTempFiles(): void
    {
        $tempDir = TEST_ROOT . '/temp';
        
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Medir tempo de execução
     */
    public static function measureExecutionTime(callable $callback): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $result = $callback();
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        return [
            'result' => $result,
            'execution_time' => ($endTime - $startTime) * 1000, // em millisegundos
            'memory_used' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage()
        ];
    }
    
    /**
     * Verificar se string contém SQL injection patterns
     */
    public static function containsSQLInjection(string $input): bool
    {
        $patterns = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION)\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/[\'\"]\s*(OR|AND)\s*[\'\"]/i',
            '/;\s*(SELECT|INSERT|UPDATE|DELETE)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar se string contém XSS patterns
     */
    public static function containsXSS(string $input): bool
    {
        $patterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b/i',
            '/<object\b/i',
            '/<embed\b/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Gerar dados para teste de carga
     */
    public static function generateLoadTestData(int $count = 100): array
    {
        $data = [];
        
        for ($i = 0; $i < $count; $i++) {
            $data[] = self::generateDIData([
                'numero_di' => self::generateDINumber() . str_pad($i, 4, '0', STR_PAD_LEFT)
            ]);
        }
        
        return $data;
    }
}

/**
 * Classe para assertions customizadas
 */
class CustomAssertions
{
    /**
     * Assert que valor está dentro do range esperado
     */
    public static function assertInRange($value, $min, $max, string $message = ''): void
    {
        if ($value < $min || $value > $max) {
            $defaultMessage = "Valor {$value} não está no range {$min}-{$max}";
            throw new InvalidArgumentException($message ?: $defaultMessage);
        }
    }
    
    /**
     * Assert que array tem estrutura esperada
     */
    public static function assertArrayStructure(array $array, array $structure, string $message = ''): void
    {
        foreach ($structure as $key => $type) {
            if (!array_key_exists($key, $array)) {
                throw new InvalidArgumentException("Chave '{$key}' não encontrada no array");
            }
            
            if ($type && gettype($array[$key]) !== $type) {
                throw new InvalidArgumentException(
                    "Chave '{$key}' deveria ser {$type}, mas é " . gettype($array[$key])
                );
            }
        }
    }
    
    /**
     * Assert que response time está dentro do esperado
     */
    public static function assertResponseTime(float $time, float $maxTime, string $operation = ''): void
    {
        if ($time > $maxTime) {
            $op = $operation ? " para {$operation}" : '';
            throw new InvalidArgumentException(
                "Tempo de resposta {$time}ms excede o limite de {$maxTime}ms{$op}"
            );
        }
    }
}