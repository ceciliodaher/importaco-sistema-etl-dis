/**
 * ================================================================================
 * XML VALIDATOR - VALIDAÇÃO ESPECÍFICA PARA DI's BRASILEIRAS
 * Features: Validação Estrutural, Campos Obrigatórios, Preview de Dados, Detecção Multi-Moeda
 * Sistema ETL DI's - Validação completa de XMLs de Declaração de Importação
 * ================================================================================
 */

class XMLValidator {
    constructor() {
        // Validation rules for Brazilian DI structure
        this.requiredFields = {
            declaracao: [
                'numero_di',
                'cpf_cnpj_adquirente',
                'uf_adquirente',
                'data_registro'
            ],
            adicao: [
                'numero_adicao',
                'codigo_ncm',
                'valor_unitario',
                'quantidade'
            ],
            mercadoria: [
                'descricao',
                'codigo_pais_origem'
            ]
        };
        
        // Brazilian tax codes validation
        this.validCodes = {
            ncm: /^\d{8}$/,
            cfop: /^\d{4}$/,
            cst: /^\d{2}$/,
            cnpj: /^\d{14}$/,
            cpf: /^\d{11}$/,
            uf: /^[A-Z]{2}$/,
            pais: /^\d{3}$/
        };
        
        // Currency codes commonly found in DI
        this.supportedCurrencies = [
            'USD', 'EUR', 'BRL', 'CNY', 'JPY', 'GBP', 'CHF',
            'CAD', 'AUD', 'INR', 'KRW', 'MXN', 'ARS', 'CLP'
        ];
        
        // Brazilian states
        this.brazilianStates = [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
            'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
            'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
        ];
        
        // ICMS tax structure by state
        this.icmsStructure = {
            'GO': { aliquota_interna: 17, beneficio: true },
            'SC': { aliquota_interna: 17, beneficio: true },
            'SP': { aliquota_interna: 18, beneficio: false },
            'RJ': { aliquota_interna: 20, beneficio: false },
            'MG': { aliquota_interna: 18, beneficio: false }
            // Add more states as needed
        };
        
        // Common DI XML namespaces and root elements
        this.diNamespaces = [
            'declaracaoImportacao',
            'DI',
            'DeclaracaoImportacao',
            'declaracao_importacao'
        ];
        
        // Validation cache
        this.validationCache = new Map();
        this.previewCache = new Map();
    }

    /**
     * Main validation method for DI XML
     */
    async validateDI(xmlContent, options = {}) {
        try {
            const startTime = performance.now();
            
            // Check cache first
            const cacheKey = this.generateCacheKey(xmlContent);
            if (this.validationCache.has(cacheKey) && !options.skipCache) {
                return this.validationCache.get(cacheKey);
            }
            
            const validation = {
                valid: true,
                errors: [],
                warnings: [],
                info: [],
                preview: null,
                currencies: [],
                statistics: {},
                validationTime: 0
            };
            
            // Step 1: Basic XML structure validation
            const xmlDoc = await this.parseXML(xmlContent);
            if (!xmlDoc.success) {
                validation.valid = false;
                validation.errors.push(xmlDoc.error);
                return validation;
            }
            
            const doc = xmlDoc.document;
            
            // Step 2: Identify DI structure
            const diStructure = this.identifyDIStructure(doc);
            if (!diStructure.success) {
                validation.valid = false;
                validation.errors.push(diStructure.error);
                return validation;
            }
            
            // Step 3: Validate required fields
            const fieldsValidation = this.validateRequiredFields(doc, diStructure.structure);
            validation.errors.push(...fieldsValidation.errors);
            validation.warnings.push(...fieldsValidation.warnings);
            
            // Step 4: Validate Brazilian-specific codes
            const codesValidation = this.validateBrazilianCodes(doc, diStructure.structure);
            validation.errors.push(...codesValidation.errors);
            validation.warnings.push(...codesValidation.warnings);
            
            // Step 5: Detect and validate currencies
            const currencyValidation = this.validateCurrencies(doc, diStructure.structure);
            validation.currencies = currencyValidation.currencies;
            validation.warnings.push(...currencyValidation.warnings);
            validation.info.push(...currencyValidation.info);
            
            // Step 6: Validate tax calculations
            const taxValidation = this.validateTaxStructure(doc, diStructure.structure);
            validation.warnings.push(...taxValidation.warnings);
            validation.info.push(...taxValidation.info);
            
            // Step 7: Generate preview data
            validation.preview = this.generatePreview(doc, diStructure.structure);
            
            // Step 8: Generate statistics
            validation.statistics = this.generateStatistics(doc, diStructure.structure);
            
            // Final validation status
            validation.valid = validation.errors.length === 0;
            validation.validationTime = performance.now() - startTime;
            
            // Cache result if valid
            if (validation.valid && !options.skipCache) {
                this.validationCache.set(cacheKey, validation);
                
                // Clean old cache entries
                if (this.validationCache.size > 50) {
                    const firstKey = this.validationCache.keys().next().value;
                    this.validationCache.delete(firstKey);
                }
            }
            
            return validation;
            
        } catch (error) {
            return {
                valid: false,
                errors: [`Erro interno na validação: ${error.message}`],
                warnings: [],
                info: [],
                preview: null,
                currencies: [],
                statistics: {},
                validationTime: 0
            };
        }
    }

    async parseXML(xmlContent) {
        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(xmlContent, 'text/xml');
            
            // Check for parsing errors
            const parserError = doc.querySelector('parsererror');
            if (parserError) {
                return {
                    success: false,
                    error: 'XML mal formado: ' + parserError.textContent
                };
            }
            
            return {
                success: true,
                document: doc
            };
        } catch (error) {
            return {
                success: false,
                error: 'Erro ao analisar XML: ' + error.message
            };
        }
    }

    identifyDIStructure(doc) {
        // Try to find DI root element
        for (const namespace of this.diNamespaces) {
            const rootElement = doc.querySelector(namespace);
            if (rootElement) {
                const structure = this.analyzeDIStructure(rootElement);
                return {
                    success: true,
                    structure: structure,
                    rootElement: rootElement,
                    namespace: namespace
                };
            }
        }
        
        // Fallback: check for common DI indicators in any element
        const indicators = [
            'numero_di', 'declaracao_importacao', 'cpf_cnpj_adquirente',
            'adicao', 'mercadoria', 'imposto'
        ];
        
        for (const indicator of indicators) {
            if (doc.querySelector(indicator)) {
                const structure = this.analyzeDIStructure(doc.documentElement);
                return {
                    success: true,
                    structure: structure,
                    rootElement: doc.documentElement,
                    namespace: 'inferred'
                };
            }
        }
        
        return {
            success: false,
            error: 'XML não parece ser uma Declaração de Importação brasileira válida'
        };
    }

    analyzeDIStructure(rootElement) {
        const structure = {
            type: 'unknown',
            version: 'unknown',
            elements: {},
            paths: {}
        };
        
        // Common DI element patterns
        const patterns = {
            declaracao: ['declaracao', 'di', 'declaracao_importacao'],
            adicoes: ['adicao', 'adicoes', 'item', 'itens'],
            mercadorias: ['mercadoria', 'mercadorias', 'produto', 'produtos'],
            impostos: ['imposto', 'impostos', 'tributo', 'tributos'],
            pagamentos: ['pagamento', 'pagamentos'],
            despesas: ['despesa', 'despesas', 'custo', 'custos']
        };
        
        // Find element paths
        for (const [category, possibleNames] of Object.entries(patterns)) {
            for (const name of possibleNames) {
                const element = rootElement.querySelector(name);
                if (element) {
                    structure.elements[category] = element.tagName;
                    structure.paths[category] = this.getElementPath(element);
                    break;
                }
            }
        }
        
        // Try to determine DI type and version
        if (rootElement.getAttribute('versao')) {
            structure.version = rootElement.getAttribute('versao');
        } else if (rootElement.getAttribute('version')) {
            structure.version = rootElement.getAttribute('version');
        }
        
        const numeroElement = rootElement.querySelector('numero_di, numero, di_numero');
        if (numeroElement) {
            const numeroText = numeroElement.textContent;
            if (numeroText.length === 11) {
                structure.type = 'di_siscomex';
            } else if (numeroText.length === 10) {
                structure.type = 'di_manual';
            }
        }
        
        return structure;
    }

    validateRequiredFields(doc, structure) {
        const errors = [];
        const warnings = [];
        
        // Validate main declaration fields
        for (const field of this.requiredFields.declaracao) {
            const element = doc.querySelector(field) || doc.querySelector(`*[name="${field}"]`);
            if (!element || !element.textContent.trim()) {
                errors.push(`Campo obrigatório ausente: ${field}`);
            } else {
                // Validate specific field formats
                this.validateFieldFormat(field, element.textContent.trim(), errors, warnings);
            }
        }
        
        // Validate adicoes (items)
        const adicoes = doc.querySelectorAll('adicao') || doc.querySelectorAll('item');
        if (adicoes.length === 0) {
            errors.push('Nenhuma adição encontrada na DI');
        } else {
            adicoes.forEach((adicao, index) => {
                for (const field of this.requiredFields.adicao) {
                    const element = adicao.querySelector(field) || adicao.querySelector(`*[name="${field}"]`);
                    if (!element || !element.textContent.trim()) {
                        errors.push(`Adição ${index + 1}: Campo obrigatório ausente: ${field}`);
                    }
                }
            });
        }
        
        return { errors, warnings };
    }

    validateFieldFormat(field, value, errors, warnings) {
        switch (field) {
            case 'cpf_cnpj_adquirente':
                if (value.length === 11 && !this.validCodes.cpf.test(value)) {
                    errors.push('CPF inválido: ' + value);
                } else if (value.length === 14 && !this.validCodes.cnpj.test(value)) {
                    errors.push('CNPJ inválido: ' + value);
                } else if (value.length !== 11 && value.length !== 14) {
                    errors.push('CPF/CNPJ deve ter 11 ou 14 dígitos: ' + value);
                }
                break;
                
            case 'uf_adquirente':
                if (!this.brazilianStates.includes(value.toUpperCase())) {
                    errors.push('UF inválida: ' + value);
                }
                break;
                
            case 'data_registro':
                const dateRegex = /^\d{4}-\d{2}-\d{2}$|^\d{2}\/\d{2}\/\d{4}$/;
                if (!dateRegex.test(value)) {
                    warnings.push('Formato de data pode estar inválido: ' + value);
                }
                break;
                
            case 'numero_di':
                if (!/^\d{10,11}$/.test(value)) {
                    errors.push('Número da DI deve ter 10 ou 11 dígitos: ' + value);
                }
                break;
        }
    }

    validateBrazilianCodes(doc, structure) {
        const errors = [];
        const warnings = [];
        
        // Validate NCM codes
        const ncmElements = doc.querySelectorAll('codigo_ncm, ncm, codigoNcm');
        ncmElements.forEach((element, index) => {
            const ncm = element.textContent.replace(/\D/g, '');
            if (!this.validCodes.ncm.test(ncm)) {
                errors.push(`NCM inválido (item ${index + 1}): ${element.textContent}`);
            }
        });
        
        // Validate CFOP codes
        const cfopElements = doc.querySelectorAll('cfop, codigo_cfop, codigoCfop');
        cfopElements.forEach((element, index) => {
            const cfop = element.textContent.replace(/\D/g, '');
            if (!this.validCodes.cfop.test(cfop)) {
                warnings.push(`CFOP pode estar inválido (item ${index + 1}): ${element.textContent}`);
            }
        });
        
        // Validate CST codes
        const cstElements = doc.querySelectorAll('cst, codigo_cst, codigoCst');
        cstElements.forEach((element, index) => {
            const cst = element.textContent.replace(/\D/g, '');
            if (!this.validCodes.cst.test(cst)) {
                warnings.push(`CST pode estar inválido (item ${index + 1}): ${element.textContent}`);
            }
        });
        
        // Validate country codes
        const paisElements = doc.querySelectorAll('codigo_pais_origem, pais_origem, codigoPais');
        paisElements.forEach((element, index) => {
            const pais = element.textContent.replace(/\D/g, '');
            if (!this.validCodes.pais.test(pais)) {
                warnings.push(`Código de país pode estar inválido (item ${index + 1}): ${element.textContent}`);
            }
        });
        
        return { errors, warnings };
    }

    validateCurrencies(doc, structure) {
        const currencies = new Set();
        const warnings = [];
        const info = [];
        
        // Common currency field patterns
        const currencyPatterns = [
            'codigo_moeda', 'moeda', 'currency', 'coin_code',
            'moeda_negociada', 'currency_code'
        ];
        
        currencyPatterns.forEach(pattern => {
            const elements = doc.querySelectorAll(pattern);
            elements.forEach(element => {
                const currency = element.textContent.trim().toUpperCase();
                if (currency) {
                    currencies.add(currency);
                    
                    if (!this.supportedCurrencies.includes(currency)) {
                        warnings.push(`Moeda não reconhecida: ${currency}`);
                    }
                }
            });
        });
        
        // Check for multiple currencies
        if (currencies.size > 1) {
            info.push(`DI com múltiplas moedas detectadas: ${Array.from(currencies).join(', ')}`);
        } else if (currencies.size === 1) {
            info.push(`Moeda principal detectada: ${Array.from(currencies)[0]}`);
        } else {
            warnings.push('Nenhuma informação de moeda encontrada');
        }
        
        // Validate currency values format
        const valueElements = doc.querySelectorAll('[*="valor"], [*="value"], valor_*, preco_*');
        valueElements.forEach(element => {
            const value = element.textContent.trim();
            if (value && !/^\d+([.,]\d+)?$/.test(value)) {
                warnings.push(`Formato de valor monetário pode estar incorreto: ${value}`);
            }
        });
        
        return {
            currencies: Array.from(currencies),
            warnings,
            info
        };
    }

    validateTaxStructure(doc, structure) {
        const warnings = [];
        const info = [];
        
        // Check for ICMS structure
        const icmsElements = doc.querySelectorAll('icms, valor_icms, aliquota_icms');
        if (icmsElements.length > 0) {
            info.push(`Estrutura ICMS encontrada (${icmsElements.length} elementos)`);
            
            // Validate UF for ICMS benefits
            const ufElement = doc.querySelector('uf_adquirente, uf_destinatario');
            if (ufElement) {
                const uf = ufElement.textContent.trim().toUpperCase();
                if (this.icmsStructure[uf] && this.icmsStructure[uf].beneficio) {
                    info.push(`Estado ${uf} possui benefícios fiscais para importação`);
                }
            }
        }
        
        // Check for II (Import Tax)
        const iiElements = doc.querySelectorAll('ii, imposto_importacao, valor_ii');
        if (iiElements.length === 0) {
            warnings.push('Imposto de Importação (II) não encontrado');
        } else {
            info.push('Estrutura do Imposto de Importação encontrada');
        }
        
        // Check for IPI
        const ipiElements = doc.querySelectorAll('ipi, valor_ipi, aliquota_ipi');
        if (ipiElements.length > 0) {
            info.push('Estrutura IPI encontrada');
        }
        
        // Check for PIS/COFINS
        const pisElements = doc.querySelectorAll('pis, valor_pis');
        const cofinsElements = doc.querySelectorAll('cofins, valor_cofins');
        
        if (pisElements.length > 0 || cofinsElements.length > 0) {
            info.push('Estrutura PIS/COFINS encontrada');
        }
        
        return { warnings, info };
    }

    generatePreview(doc, structure) {
        const preview = {
            declaracao: {},
            adicoes: [],
            resumo: {},
            moedas: [],
            impostos: {}
        };
        
        try {
            // Basic declaration info
            const fieldsToExtract = [
                'numero_di', 'cpf_cnpj_adquirente', 'data_registro',
                'uf_adquirente', 'nome_adquirente'
            ];
            
            fieldsToExtract.forEach(field => {
                const element = doc.querySelector(field) || doc.querySelector(`*[name="${field}"]`);
                if (element) {
                    preview.declaracao[field] = element.textContent.trim();
                }
            });
            
            // Extract adicoes/items info
            const adicoes = doc.querySelectorAll('adicao, item');
            adicoes.forEach((adicao, index) => {
                const adicaoData = {
                    numero: index + 1,
                    ncm: this.extractText(adicao, 'codigo_ncm, ncm'),
                    descricao: this.extractText(adicao, 'descricao, descricao_mercadoria'),
                    quantidade: this.extractText(adicao, 'quantidade, qtd'),
                    valor_unitario: this.extractText(adicao, 'valor_unitario, preco_unitario'),
                    moeda: this.extractText(adicao, 'codigo_moeda, moeda'),
                    pais_origem: this.extractText(adicao, 'codigo_pais_origem, pais_origem')
                };
                
                preview.adicoes.push(adicaoData);
            });
            
            // Generate summary
            preview.resumo = {
                total_adicoes: preview.adicoes.length,
                moedas_detectadas: [...new Set(preview.adicoes.map(a => a.moeda).filter(Boolean))],
                valor_total: this.calculateTotalValue(preview.adicoes)
            };
            
            // Extract tax information
            const taxFields = ['ii', 'ipi', 'pis', 'cofins', 'icms'];
            taxFields.forEach(tax => {
                const elements = doc.querySelectorAll(`${tax}, valor_${tax}, aliquota_${tax}`);
                if (elements.length > 0) {
                    preview.impostos[tax] = {
                        encontrado: true,
                        elementos: elements.length
                    };
                }
            });
            
        } catch (error) {
            console.warn('Error generating preview:', error);
        }
        
        return preview;
    }

    generateStatistics(doc, structure) {
        const stats = {
            xml_size: 0,
            elements_count: 0,
            adicoes_count: 0,
            currencies_count: 0,
            tax_elements: 0,
            required_fields_found: 0,
            validation_score: 0
        };
        
        try {
            // Basic XML statistics
            const allElements = doc.querySelectorAll('*');
            stats.elements_count = allElements.length;
            
            // Count adicoes
            const adicoes = doc.querySelectorAll('adicao, item');
            stats.adicoes_count = adicoes.length;
            
            // Count currencies
            const currencies = new Set();
            const currencyElements = doc.querySelectorAll('codigo_moeda, moeda, currency');
            currencyElements.forEach(el => currencies.add(el.textContent.trim().toUpperCase()));
            stats.currencies_count = currencies.size;
            
            // Count tax elements
            const taxElements = doc.querySelectorAll('ii, ipi, pis, cofins, icms, imposto, tributo');
            stats.tax_elements = taxElements.length;
            
            // Count required fields found
            const requiredFieldsTotal = this.requiredFields.declaracao.length;
            let foundFields = 0;
            
            this.requiredFields.declaracao.forEach(field => {
                const element = doc.querySelector(field);
                if (element && element.textContent.trim()) {
                    foundFields++;
                }
            });
            
            stats.required_fields_found = foundFields;
            stats.validation_score = Math.round((foundFields / requiredFieldsTotal) * 100);
            
        } catch (error) {
            console.warn('Error generating statistics:', error);
        }
        
        return stats;
    }

    // Utility methods
    extractText(parent, selectors) {
        const selectorList = selectors.split(', ');
        for (const selector of selectorList) {
            const element = parent.querySelector(selector.trim());
            if (element && element.textContent.trim()) {
                return element.textContent.trim();
            }
        }
        return '';
    }

    calculateTotalValue(adicoes) {
        let total = 0;
        adicoes.forEach(adicao => {
            const quantidade = parseFloat(adicao.quantidade) || 0;
            const valorUnitario = parseFloat(adicao.valor_unitario?.replace(',', '.')) || 0;
            total += quantidade * valorUnitario;
        });
        return total.toFixed(2);
    }

    getElementPath(element) {
        const path = [];
        let current = element;
        
        while (current && current !== document) {
            path.unshift(current.tagName.toLowerCase());
            current = current.parentElement;
        }
        
        return path.join(' > ');
    }

    generateCacheKey(xmlContent) {
        // Generate a simple hash for caching
        let hash = 0;
        for (let i = 0; i < xmlContent.length; i++) {
            const char = xmlContent.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        return hash.toString();
    }

    // Public API methods
    async validateFile(file) {
        try {
            const xmlContent = await this.readFileAsText(file);
            return await this.validateDI(xmlContent);
        } catch (error) {
            return {
                valid: false,
                errors: [`Erro ao ler arquivo: ${error.message}`],
                warnings: [],
                info: [],
                preview: null,
                currencies: [],
                statistics: {}
            };
        }
    }

    readFileAsText(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = (e) => reject(new Error('Erro ao ler arquivo'));
            reader.readAsText(file);
        });
    }

    async quickValidate(xmlContent) {
        // Quick validation for basic structure only
        try {
            const xmlDoc = await this.parseXML(xmlContent);
            if (!xmlDoc.success) {
                return { valid: false, error: xmlDoc.error };
            }
            
            const structure = this.identifyDIStructure(xmlDoc.document);
            if (!structure.success) {
                return { valid: false, error: structure.error };
            }
            
            return { valid: true, structure: structure.structure };
        } catch (error) {
            return { valid: false, error: error.message };
        }
    }

    getValidationSummary(validationResult) {
        if (!validationResult) return 'Nenhum resultado de validação';
        
        const { valid, errors, warnings, info, statistics } = validationResult;
        
        let summary = valid ? '✅ XML Válido' : '❌ XML Inválido';
        
        if (errors.length > 0) {
            summary += `\n🚨 ${errors.length} erro(s)`;
        }
        
        if (warnings.length > 0) {
            summary += `\n⚠️ ${warnings.length} aviso(s)`;
        }
        
        if (info.length > 0) {
            summary += `\nℹ️ ${info.length} informação(ões)`;
        }
        
        if (statistics.validation_score !== undefined) {
            summary += `\n📊 Score: ${statistics.validation_score}%`;
        }
        
        return summary;
    }

    // Clear caches
    clearCache() {
        this.validationCache.clear();
        this.previewCache.clear();
    }

    // Get cache statistics
    getCacheStats() {
        return {
            validation_cache_size: this.validationCache.size,
            preview_cache_size: this.previewCache.size,
            cache_memory_usage: this.estimateCacheSize()
        };
    }

    estimateCacheSize() {
        let size = 0;
        for (const [key, value] of this.validationCache) {
            size += JSON.stringify(value).length;
        }
        return `${Math.round(size / 1024)}KB`;
    }
}

// Initialize XML validator when DOM is ready
let xmlValidator;

document.addEventListener('DOMContentLoaded', function() {
    xmlValidator = new XMLValidator();
    
    // Make available globally
    window.XMLValidator = XMLValidator;
    window.xmlValidator = xmlValidator;
    
    // Integration with upload manager
    if (window.uploadManager) {
        console.log('XML Validator integrado com Upload Manager');
    }
    
    console.log('XML Validator para DI brasileiras inicializado');
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = XMLValidator;
}