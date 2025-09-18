<?php
/**
 * ================================================================================
 * DI XML PARSER - BASEADO EM ANÁLISE DE XMLs REAIS
 * Sistema ETL de DI's - DI como fonte da verdade, sistema compara e calcula
 * Baseado em análise real dos XMLs: 2518173187.xml, 2520345968.xml, 2300120746.xml
 * ================================================================================
 */

require_once __DIR__ . '/../../config/database.php';

class DiXmlParser {
    
    private $db;
    private $debug = false;
    
    public function __construct($debug = false) {
        $this->debug = $debug;
        try {
            $this->db = getDatabase();
        } catch (Exception $e) {
            throw new Exception("Falha na conexão com banco: " . $e->getMessage());
        }
    }
    
    /**
     * Parser principal - extrai valores REAIS da DI (fonte da verdade)
     */
    public function parseXml($xmlPath) {
        if (!file_exists($xmlPath)) {
            throw new Exception("Arquivo XML não encontrado: $xmlPath");
        }
        
        // Ler XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlPath);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            $errorMsg = "XML inválido: ";
            foreach ($errors as $error) {
                $errorMsg .= $error->message . " ";
            }
            throw new Exception($errorMsg);
        }
        
        // Estrutura identificada: <ListaDeclaracoes><declaracaoImportacao>
        $di = $xml->declaracaoImportacao ?? $xml;
        
        if (!$di) {
            throw new Exception("Estrutura XML não reconhecida - elemento declaracaoImportacao não encontrado");
        }
        
        return $this->extractRealValues($di);
    }
    
    /**
     * Extrai valores REAIS da DI conforme análise dos XMLs brasileiros
     */
    private function extractRealValues($di) {
        // Dados principais da DI
        $data = [
            'numero_di' => $this->getElementValue($di->numeroDI),
            'data_registro' => $this->convertSiscomexDate($di->dataRegistro),
            'importador_cnpj' => $this->extractCnpj($di),
            'importador_nome' => $this->extractImportadorNome($di),
            'total_adicoes' => 0,
            'valor_total_cif_usd' => 0,
            'valor_total_cif_brl' => 0,
            'adicoes' => []
        ];
        
        // Extrair adições
        $adicoes = $di->adicao ?? [];
        if (!is_array($adicoes)) {
            $adicoes = [$adicoes]; // XML com uma só adição
        }
        
        $data['total_adicoes'] = count($adicoes);
        
        foreach ($adicoes as $adicao) {
            $adicaoData = $this->extractAdicao($adicao);
            $data['adicoes'][] = $adicaoData;
            
            // Somar totais
            $data['valor_total_cif_usd'] += $adicaoData['valor_vmcv_moeda'];
            $data['valor_total_cif_brl'] += $adicaoData['valor_vmcv_reais'];
        }
        
        if ($this->debug) {
            error_log("DiXmlParser: Extraído DI {$data['numero_di']} com {$data['total_adicoes']} adições");
        }
        
        return $data;
    }
    
    /**
     * Extrai dados de uma adição (baseado na estrutura XML real)
     */
    private function extractAdicao($adicao) {
        return [
            'numero_adicao' => $this->getElementValue($adicao->numeroAdicao),
            'ncm' => $this->getElementValue($adicao->dadosMercadoriaCodigoNcm),
            
            // INCOTERM - CAMPO CRÍTICO
            'incoterm' => $this->getElementValue($adicao->condicaoVendaIncoterm),
            'condicao_venda_local' => $this->getElementValue($adicao->condicaoVendaLocal),
            
            // Valores VMLE e VMCV (conversão Siscomex ÷ 100)
            'valor_vmle_moeda' => $this->convertSiscomexValue($adicao->localEmbarqueValorMoeda ?? $adicao->condicaoVendaValorMoeda),
            'valor_vmle_reais' => $this->convertSiscomexValue($adicao->localEmbarqueValorReais ?? $adicao->condicaoVendaValorReais),
            'valor_vmcv_moeda' => $this->convertSiscomexValue($adicao->condicaoVendaValorMoeda),
            'valor_vmcv_reais' => $this->convertSiscomexValue($adicao->condicaoVendaValorReais),
            
            // Moeda
            'moeda_codigo' => $this->convertMoedaCode($adicao->condicaoVendaMoedaCodigo),
            'moeda_nome' => $this->getElementValue($adicao->condicaoVendaMoedaNome),
            
            // Pesos (conversão ÷ 1000)
            'peso_liquido' => $this->convertSiscomexWeight($adicao->dadosMercadoriaPesoLiquido),
            'peso_bruto' => $this->convertSiscomexWeight($adicao->dadosCargaPesoBruto),
            
            // IMPOSTOS REAIS DA DI (fonte da verdade)
            'impostos' => [
                'ii' => [
                    'valor_di' => $this->convertSiscomexValue($adicao->iiAliquotaValorDevido),
                    'base_di' => $this->convertSiscomexValue($adicao->iiBaseCalculo),
                    'aliquota_di' => $this->convertSiscomexRate($adicao->iiAliquotaAdValorem)
                ],
                'ipi' => [
                    'valor_di' => $this->convertSiscomexValue($adicao->ipiAliquotaValorDevido),
                    'aliquota_di' => $this->convertSiscomexRate($adicao->ipiAliquotaAdValorem)
                ],
                'pis' => [
                    'valor_di' => $this->convertSiscomexValue($adicao->pisPasepAliquotaValorDevido),
                    'base_di' => $this->convertSiscomexValue($adicao->pisCofinsBaseCalculoValor),
                    'aliquota_di' => $this->convertSiscomexRate($adicao->pisPasepAliquotaAdValorem)
                ],
                'cofins' => [
                    'valor_di' => $this->convertSiscomexValue($adicao->cofinsAliquotaValorDevido),
                    'aliquota_di' => $this->convertSiscomexRate($adicao->cofinsAliquotaAdValorem)
                ]
            ],
            
            // Mercadorias
            'mercadorias' => $this->extractMercadorias($adicao)
        ];
    }
    
    /**
     * Extrai mercadorias de uma adição
     */
    private function extractMercadorias($adicao) {
        $mercadorias = [];
        
        // XML pode ter array de mercadorias ou mercadoria única
        $mercadoriaElements = $adicao->mercadoria ?? [];
        if (!is_array($mercadoriaElements)) {
            $mercadoriaElements = [$mercadoriaElements];
        }
        
        foreach ($mercadoriaElements as $merc) {
            $mercadorias[] = [
                'numero_sequencial' => $this->getElementValue($merc->numeroSequencialItem),
                'descricao' => $this->getElementValue($merc->descricaoMercadoria),
                'quantidade' => $this->convertSiscomexQuantity($merc->quantidade),
                'unidade_medida' => $this->getElementValue($merc->unidadeMedida),
                'valor_unitario' => $this->convertSiscomexUnitValue($merc->valorUnitario)
            ];
        }
        
        return $mercadorias;
    }
    
    /**
     * Converte valores Siscomex (formato: 000000000089364 = 893.64)
     */
    private function convertSiscomexValue($value) {
        if (!$value) return 0.00;
        $num = (string) $value;
        // Remove leading zeros and convert
        $cleanNum = ltrim($num, '0') ?: '0';
        return round((float) $cleanNum / 100, 2);
    }
    
    /**
     * Converte pesos Siscomex (÷ 1000)
     */
    private function convertSiscomexWeight($value) {
        if (!$value) return 0.000;
        $num = (string) $value;
        return round((float) $num / 1000, 3);
    }
    
    /**
     * Converte alíquotas Siscomex (formato: 00965 = 9.65%)
     */
    private function convertSiscomexRate($value) {
        if (!$value) return 0.0000;
        $num = (string) $value;
        // SISCOMEX usa centésimos de percentual: 965 = 9.65% = 0.0965 decimal
        return round((float) $num / 10000, 6);
    }
    
    /**
     * Converte quantidade Siscomex
     */
    private function convertSiscomexQuantity($value) {
        if (!$value) return 0.00000;
        $num = (string) $value;
        return round((float) $num / 100000, 5);
    }
    
    /**
     * Converte valor unitário Siscomex
     */
    private function convertSiscomexUnitValue($value) {
        if (!$value) return 0.00000000;
        $num = (string) $value;
        return round((float) $num / 10000000000, 8);
    }
    
    /**
     * Converte data Siscomex (YYYYMMDD -> Y-m-d)
     */
    private function convertSiscomexDate($value) {
        if (!$value) return null;
        $dateStr = (string) $value;
        if (strlen($dateStr) === 8) {
            return substr($dateStr, 0, 4) . '-' . substr($dateStr, 4, 2) . '-' . substr($dateStr, 6, 2);
        }
        return null;
    }
    
    /**
     * Converte código de moeda Siscomex para ISO
     */
    private function convertMoedaCode($code) {
        // Manter código SISCOMEX para foreign key com moedas_referencia
        return (string) $code;
    }
    
    /**
     * Extrai CNPJ do importador
     */
    private function extractCnpj($di) {
        // Pode estar em diferentes lugares dependendo da estrutura
        return $this->getElementValue($di->importadorNumero) ?? 
               $this->getElementValue($di->importador->numeroInscricao) ?? 
               '';
    }
    
    /**
     * Extrai nome do importador
     */
    private function extractImportadorNome($di) {
        return $this->getElementValue($di->importadorNome) ?? 
               $this->getElementValue($di->importador->nome) ?? 
               '';
    }
    
    /**
     * Obtém valor de elemento XML de forma segura
     */
    private function getElementValue($element) {
        if ($element === null) return '';
        return trim((string) $element);
    }
    
    /**
     * Salva dados extraídos no banco (valores reais da DI)
     */
    public function saveToDatabase($data) {
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Inserir DI principal
            $this->insertDeclaracaoImportacao($data);
            
            // Inserir adições e impostos
            foreach ($data['adicoes'] as $adicao) {
                $adicaoId = $this->insertAdicao($data['numero_di'], $adicao);
                $this->insertImpostos($adicaoId, $adicao['impostos']);
                $this->insertMercadorias($adicaoId, $adicao['mercadorias']);
            }
            
            $this->db->getConnection()->commit();
            
            if ($this->debug) {
                error_log("DiXmlParser: Dados salvos no banco - DI {$data['numero_di']}");
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollback();
            throw new Exception("Erro ao salvar no banco: " . $e->getMessage());
        }
    }
    
    /**
     * Insere declaração de importação
     */
    private function insertDeclaracaoImportacao($data) {
        $sql = "INSERT INTO declaracoes_importacao 
                (numero_di, data_registro, importador_cnpj, importador_nome, 
                 total_adicoes, valor_total_cif_usd, valor_total_cif_brl, status_processamento)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'COMPLETO')
                ON DUPLICATE KEY UPDATE
                data_registro = VALUES(data_registro),
                importador_cnpj = VALUES(importador_cnpj),
                importador_nome = VALUES(importador_nome),
                total_adicoes = VALUES(total_adicoes),
                valor_total_cif_usd = VALUES(valor_total_cif_usd),
                valor_total_cif_brl = VALUES(valor_total_cif_brl),
                status_processamento = 'COMPLETO',
                updated_at = CURRENT_TIMESTAMP";
                
        $this->db->query($sql, [
            $data['numero_di'],
            $data['data_registro'],
            $data['importador_cnpj'],
            $data['importador_nome'],
            $data['total_adicoes'],
            $data['valor_total_cif_usd'],
            $data['valor_total_cif_brl']
        ]);
    }
    
    /**
     * Insere adição
     */
    private function insertAdicao($numeroDi, $adicao) {
        $sql = "INSERT INTO adicoes 
                (numero_di, numero_adicao, ncm, valor_vmle_moeda, valor_vmle_reais,
                 valor_vmcv_moeda, valor_vmcv_reais, moeda_codigo, moeda_nome, 
                 incoterm, condicao_venda_local, peso_liquido, peso_bruto)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                ncm = VALUES(ncm),
                valor_vmle_moeda = VALUES(valor_vmle_moeda),
                valor_vmle_reais = VALUES(valor_vmle_reais),
                valor_vmcv_moeda = VALUES(valor_vmcv_moeda),
                valor_vmcv_reais = VALUES(valor_vmcv_reais),
                moeda_codigo = VALUES(moeda_codigo),
                moeda_nome = VALUES(moeda_nome),
                incoterm = VALUES(incoterm),
                condicao_venda_local = VALUES(condicao_venda_local),
                peso_liquido = VALUES(peso_liquido),
                peso_bruto = VALUES(peso_bruto)";
                
        $this->db->query($sql, [
            $numeroDi,
            $adicao['numero_adicao'],
            $adicao['ncm'],
            $adicao['valor_vmle_moeda'] ?? 0,
            $adicao['valor_vmle_reais'] ?? 0,
            $adicao['valor_vmcv_moeda'],
            $adicao['valor_vmcv_reais'],
            $adicao['moeda_codigo'],
            $adicao['moeda_nome'],
            $adicao['incoterm'],
            $adicao['condicao_venda_local'],
            $adicao['peso_liquido'],
            $adicao['peso_bruto']
        ]);
        
        // Retornar ID da adição
        $result = $this->db->fetchOne("SELECT id FROM adicoes WHERE numero_di = ? AND numero_adicao = ?", 
                                      [$numeroDi, $adicao['numero_adicao']]);
        return $result['id'];
    }
    
    /**
     * Insere impostos (valores REAIS da DI - fonte da verdade)
     */
    private function insertImpostos($adicaoId, $impostos) {
        foreach ($impostos as $tipo => $imposto) {
            $sql = "INSERT INTO impostos_adicao 
                    (adicao_id, tipo_imposto, valor_devido_reais, base_calculo, aliquota_ad_valorem)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    valor_devido_reais = VALUES(valor_devido_reais),
                    base_calculo = VALUES(base_calculo),
                    aliquota_ad_valorem = VALUES(aliquota_ad_valorem)";
                    
            $this->db->query($sql, [
                $adicaoId,
                strtoupper($tipo),
                $imposto['valor_di'],
                $imposto['base_di'] ?? null,
                $imposto['aliquota_di'] ?? null
            ]);
        }
    }
    
    /**
     * Insere mercadorias
     */
    private function insertMercadorias($adicaoId, $mercadorias) {
        foreach ($mercadorias as $mercadoria) {
            $sql = "INSERT INTO mercadorias 
                    (adicao_id, numero_sequencial, descricao, quantidade, unidade_medida)
                    VALUES (?, ?, ?, ?, ?)";
                    
            $this->db->query($sql, [
                $adicaoId,
                $mercadoria['numero_sequencial'],
                $mercadoria['descricao'],
                $mercadoria['quantidade'],
                $mercadoria['unidade_medida']
            ]);
        }
    }
    
    /**
     * Calcula valores teóricos (para comparação com DI)
     */
    public function calculateTheoreticalValues($diData) {
        // TODO: Implementar engine de cálculo baseado nas regras do POP
        // Por enquanto retorna os mesmos valores (sem divergência)
        return $diData;
    }
    
    /**
     * Compara valores reais vs calculados
     */
    public function analyzeDivergences($realValues, $calculatedValues) {
        $divergences = [];
        
        foreach ($realValues['adicoes'] as $i => $adicaoReal) {
            $adicaoCalc = $calculatedValues['adicoes'][$i] ?? [];
            
            foreach ($adicaoReal['impostos'] as $tipo => $impostoReal) {
                $impostoCalc = $adicaoCalc['impostos'][$tipo] ?? ['valor_di' => 0];
                
                $divergencia = $impostoReal['valor_di'] - $impostoCalc['valor_di'];
                $divergenciaPct = $impostoReal['valor_di'] > 0 ? 
                    abs($divergencia / $impostoReal['valor_di']) * 100 : 0;
                
                if ($divergenciaPct > 1) { // Mais de 1% de divergência
                    $divergences[] = [
                        'adicao' => $adicaoReal['numero_adicao'],
                        'imposto' => $tipo,
                        'valor_di' => $impostoReal['valor_di'],
                        'valor_calculado' => $impostoCalc['valor_di'],
                        'divergencia' => $divergencia,
                        'divergencia_pct' => $divergenciaPct
                    ];
                }
            }
        }
        
        return $divergences;
    }
    
    /**
     * Registra processamento de arquivo XML
     */
    public function registerProcessing($filePath, $numeroDi, $incoterm) {
        $hash = md5_file($filePath);
        $filename = basename($filePath);
        $size = filesize($filePath);
        
        $sql = "INSERT INTO processamento_xmls 
                (hash_arquivo, nome_arquivo, numero_di, incoterm, tamanho_arquivo, status_processamento)
                VALUES (?, ?, ?, ?, ?, 'COMPLETO')
                ON DUPLICATE KEY UPDATE
                numero_di = VALUES(numero_di),
                incoterm = VALUES(incoterm),
                status_processamento = 'COMPLETO',
                data_processamento = CURRENT_TIMESTAMP";
                
        $this->db->query($sql, [$hash, $filename, $numeroDi, $incoterm, $size]);
    }
}

/**
 * Função de conveniência para processar XML
 */
function processarDI($xmlPath, $debug = false) {
    try {
        $parser = new DiXmlParser($debug);
        
        // Extrair dados reais da DI
        $diData = $parser->parseXml($xmlPath);
        
        // Salvar no banco
        $parser->saveToDatabase($diData);
        
        // Registrar processamento
        $parser->registerProcessing($xmlPath, $diData['numero_di'], 
                                   $diData['adicoes'][0]['incoterm'] ?? '');
        
        return [
            'success' => true,
            'numero_di' => $diData['numero_di'],
            'total_adicoes' => $diData['total_adicoes'],
            'valor_total' => $diData['valor_total_cif_brl']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}