<?php
/**
 * Generador de archivos .mo más robusto
 * Basado en la clase MO de WordPress
 */

class Simple_MO_Generator {
    
    public function parse_po_file($po_file) {
        $entries = [];
        $lines = file($po_file, FILE_IGNORE_NEW_LINES);
        
        $current = [
            'msgid' => '',
            'msgid_plural' => '',
            'msgstr' => [],
            'msgctxt' => ''
        ];
        
        $state = null;
        $msgstr_index = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and comments (but keep translator comments for context)
            if (empty($line) || $line[0] === '#') {
                // Save previous entry when we hit a blank line or comment
                if ($state && !empty($current['msgid'])) {
                    $entries[] = $current;
                    $current = [
                        'msgid' => '',
                        'msgid_plural' => '',
                        'msgstr' => [],
                        'msgctxt' => ''
                    ];
                    $state = null;
                }
                continue;
            }
            
            // msgctxt
            if (preg_match('/^msgctxt\s+"(.*)"/', $line, $matches)) {
                $current['msgctxt'] = $this->decode_string($matches[1]);
                $state = 'msgctxt';
                continue;
            }
            
            // msgid
            if (preg_match('/^msgid\s+"(.*)"/', $line, $matches)) {
                if ($state && !empty($current['msgid'])) {
                    $entries[] = $current;
                    $current = [
                        'msgid' => '',
                        'msgid_plural' => '',
                        'msgstr' => [],
                        'msgctxt' => ''
                    ];
                }
                $current['msgid'] = $this->decode_string($matches[1]);
                $state = 'msgid';
                continue;
            }
            
            // msgid_plural
            if (preg_match('/^msgid_plural\s+"(.*)"/', $line, $matches)) {
                $current['msgid_plural'] = $this->decode_string($matches[1]);
                $state = 'msgid_plural';
                continue;
            }
            
            // msgstr[N]
            if (preg_match('/^msgstr\[(\d+)\]\s+"(.*)"/', $line, $matches)) {
                $msgstr_index = (int)$matches[1];
                $current['msgstr'][$msgstr_index] = $this->decode_string($matches[2]);
                $state = 'msgstr_plural';
                continue;
            }
            
            // msgstr
            if (preg_match('/^msgstr\s+"(.*)"/', $line, $matches)) {
                $current['msgstr'][0] = $this->decode_string($matches[1]);
                $state = 'msgstr';
                continue;
            }
            
            // Continuation
            if (preg_match('/^"(.*)"/', $line, $matches)) {
                $continuation = $this->decode_string($matches[1]);
                switch ($state) {
                    case 'msgctxt':
                        $current['msgctxt'] .= $continuation;
                        break;
                    case 'msgid':
                        $current['msgid'] .= $continuation;
                        break;
                    case 'msgid_plural':
                        $current['msgid_plural'] .= $continuation;
                        break;
                    case 'msgstr':
                        $current['msgstr'][0] .= $continuation;
                        break;
                    case 'msgstr_plural':
                        $current['msgstr'][$msgstr_index] .= $continuation;
                        break;
                }
            }
        }
        
        // Save last entry
        if (!empty($current['msgid'])) {
            $entries[] = $current;
        }
        
        return $entries;
    }
    
    private function decode_string($str) {
        return stripcslashes($str);
    }
    
    public function generate_mo($entries, $output_file) {
        // Prepare entries for MO format
        $strings = [];
        
        foreach ($entries as $entry) {
            if (empty($entry['msgid'])) {
                continue; // Skip header
            }
            
            // Build key
            $key = $entry['msgid'];
            if (!empty($entry['msgid_plural'])) {
                $key .= "\0" . $entry['msgid_plural'];
            }
            
            // Build value
            $value = implode("\0", $entry['msgstr']);
            
            // Skip untranslated
            if (empty($value) || $value === $key) {
                continue;
            }
            
            $strings[$key] = $value;
        }
        
        // Sort by key for binary search efficiency
        ksort($strings);
        
        // Build MO file
        $offsets = [];
        $ids = '';
        $strs = '';
        
        foreach ($strings as $id => $str) {
            $offsets[] = [
                strlen($ids),
                strlen($id),
                strlen($strs),
                strlen($str)
            ];
            $ids .= $id . "\0";
            $strs .= $str . "\0";
        }
        
        $key_start = 7 * 4 + count($offsets) * 4 * 4;
        $value_start = $key_start + strlen($ids);
        
        // Header
        $output = pack('Iiiiiii',
            0x950412de,           // magic number
            0,                    // version
            count($offsets),      // number of entries
            7 * 4,                // offset of key index
            7 * 4 + count($offsets) * 8,  // offset of value index
            0,                    // size of hash table
            $key_start            // offset of key table
        );
        
        // Key index
        foreach ($offsets as $offset) {
            $output .= pack('ii', $offset[1], $key_start + $offset[0]);
        }
        
        // Value index
        foreach ($offsets as $offset) {
            $output .= pack('ii', $offset[3], $value_start + $offset[2]);
        }
        
        // Strings
        $output .= $ids . $strs;
        
        file_put_contents($output_file, $output);
        
        return [
            'entries' => count($offsets),
            'size' => strlen($output)
        ];
    }
}

// Uso
$generator = new Simple_MO_Generator();
$po_file = __DIR__ . '/languages/wp-ongoing-image-weight-tracker-es_ES.po';
$mo_file = __DIR__ . '/languages/wp-ongoing-image-weight-tracker-es_ES.mo';

echo "Leyendo archivo .po..." . PHP_EOL;
$entries = $generator->parse_po_file($po_file);
echo "Entradas leídas: " . count($entries) . PHP_EOL;

echo "Generando archivo .mo..." . PHP_EOL;
$result = $generator->generate_mo($entries, $mo_file);
echo "Entradas traducidas: " . $result['entries'] . PHP_EOL;
echo "Tamaño del archivo: " . $result['size'] . " bytes" . PHP_EOL;
echo "Archivo generado: " . $mo_file . PHP_EOL;
