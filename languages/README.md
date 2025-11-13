# Sistema de Traducciones - Image Weight Tracker

## 📁 Archivos de Traducción

### Archivos actuales:

- **`wp-ongoing-image-weight-tracker.pot`** - Plantilla base con todas las cadenas traducibles
- **`wp-ongoing-image-weight-tracker-es_ES.po`** - Traducciones al español (formato legible)
- **`wp-ongoing-image-weight-tracker-es_ES.mo`** - Traducciones compiladas (binario para PHP)
- **`wp-ongoing-image-weight-tracker-es_ES-wpoiwt-admin.json`** - Traducciones para JavaScript

---

## 🔧 Cómo Funciona

### Traducciones PHP
WordPress carga automáticamente el archivo `.mo` cuando el idioma del sitio es español (`es_ES`).

**Funciones usadas:**
- `__('texto', 'wp-ongoing-image-weight-tracker')` - Devuelve traducción
- `_e('texto', 'wp-ongoing-image-weight-tracker')` - Imprime traducción
- `_n('singular', 'plural', $count, 'wp-ongoing-image-weight-tracker')` - Plurales
- `sprintf(__('Texto %s', 'domain'), $variable)` - Con variables

### Traducciones JavaScript
WordPress carga automáticamente el archivo `.json` para scripts que usan `wp.i18n`.

**Funciones usadas:**
- `__('texto', 'wp-ongoing-image-weight-tracker')` - Devuelve traducción
- `_n('singular', 'plural', count, 'wp-ongoing-image-weight-tracker')` - Plurales
- `sprintf(__('Texto %s', 'domain'), variable)` - Con variables

---

## ✅ Cambios Realizados

### Eliminado:
❌ Bloque temporal `wp.i18n.setLocaleData()` en `class-admin-page.php`

Ya no es necesario porque WordPress carga las traducciones desde los archivos `.mo` y `.json` automáticamente.

### Mantenido:
✅ `wp_set_script_translations()` en `class-admin-page.php`

Esta función le indica a WordPress dónde encontrar las traducciones JavaScript.

---

## 🌍 Añadir Nuevos Idiomas

### Opción 1: Con WP-CLI (recomendado)

```bash
# Generar archivo .pot actualizado
wp i18n make-pot . languages/wp-ongoing-image-weight-tracker.pot

# Crear nuevo idioma (ejemplo: francés)
cp languages/wp-ongoing-image-weight-tracker.pot languages/wp-ongoing-image-weight-tracker-fr_FR.po

# Editar el .po con traducciones

# Compilar .mo
msgfmt languages/wp-ongoing-image-weight-tracker-fr_FR.po -o languages/wp-ongoing-image-weight-tracker-fr_FR.mo

# Generar JSON para JavaScript
wp i18n make-json languages --no-purge
```

### Opción 2: Sin WP-CLI (manual)

#### Paso 1: Actualizar plantilla .pot
Edita manualmente `wp-ongoing-image-weight-tracker.pot` añadiendo nuevas cadenas traducibles.

#### Paso 2: Crear/editar archivo .po
Copia el `.pot` y renómbralo según el idioma:
```bash
cp wp-ongoing-image-weight-tracker.pot wp-ongoing-image-weight-tracker-fr_FR.po
```

Edita el `.po` con las traducciones en el nuevo idioma.

#### Paso 3: Compilar .mo
Usa PHP para compilar (sin necesidad de msgfmt):

```bash
php -r "
\$poFile = 'wp-ongoing-image-weight-tracker-fr_FR.po';
\$moFile = 'wp-ongoing-image-weight-tracker-fr_FR.mo';

\$lines = file(\$poFile, FILE_IGNORE_NEW_LINES);
\$entries = [];
\$currentMsgid = null;
\$currentMsgstr = null;
\$currentMsgidPlural = null;
\$currentMsgstrPlural = [];

foreach (\$lines as \$line) {
    \$line = trim(\$line);
    if (empty(\$line) || \$line[0] === '#') continue;
    
    if (preg_match('/^msgid \"(.*)\"$/', \$line, \$matches)) {
        if (\$currentMsgid !== null && \$currentMsgid !== '') {
            if (\$currentMsgidPlural !== null) {
                \$entries[\$currentMsgid] = implode(\"\0\", \$currentMsgstrPlural);
            } else {
                \$entries[\$currentMsgid] = \$currentMsgstr ?? '';
            }
        }
        \$currentMsgid = stripcslashes(\$matches[1]);
        \$currentMsgstr = null;
        \$currentMsgidPlural = null;
        \$currentMsgstrPlural = [];
        continue;
    }
    
    if (preg_match('/^msgid_plural \"(.*)\"$/', \$line, \$matches)) {
        \$currentMsgidPlural = stripcslashes(\$matches[1]);
        continue;
    }
    
    if (preg_match('/^msgstr\[(\d+)\] \"(.*)\"$/', \$line, \$matches)) {
        \$currentMsgstrPlural[(int)\$matches[1]] = stripcslashes(\$matches[2]);
        continue;
    }
    
    if (preg_match('/^msgstr \"(.*)\"$/', \$line, \$matches)) {
        \$currentMsgstr = stripcslashes(\$matches[1]);
        continue;
    }
}

if (\$currentMsgid !== null && \$currentMsgid !== '') {
    if (\$currentMsgidPlural !== null) {
        \$entries[\$currentMsgid] = implode(\"\0\", \$currentMsgstrPlural);
    } else {
        \$entries[\$currentMsgid] = \$currentMsgstr ?? '';
    }
}

\$mo = pack('V', 0x950412de) . pack('V', 0);
\$numStrings = count(\$entries);
\$mo .= pack('V', \$numStrings);
\$origOffset = 28;
\$mo .= pack('V', \$origOffset);
\$transOffset = \$origOffset + 8 * \$numStrings;
\$mo .= pack('V', \$transOffset);
\$mo .= pack('V', 0) . pack('V', \$transOffset + 8 * \$numStrings);

\$origTable = \$transTable = \$origStrings = \$transStrings = '';
\$offset = \$transOffset + 8 * \$numStrings;

foreach (\$entries as \$msgid => \$msgstr) {
    \$origTable .= pack('V', strlen(\$msgid)) . pack('V', \$offset);
    \$origStrings .= \$msgid . \"\0\";
    \$offset += strlen(\$msgid) + 1;
    \$transTable .= pack('V', strlen(\$msgstr)) . pack('V', \$offset);
    \$transStrings .= \$msgstr . \"\0\";
    \$offset += strlen(\$msgstr) + 1;
}

\$mo .= \$origTable . \$transTable . \$origStrings . \$transStrings;
file_put_contents(\$moFile, \$mo);
echo 'Archivo .mo generado: ' . \$moFile . PHP_EOL;
"
```

#### Paso 4: Crear JSON para JavaScript
```bash
php -r "
\$locale = 'fr_FR'; // Cambiar según idioma
\$handle = 'wpoiwt-admin';
\$domain = 'wp-ongoing-image-weight-tracker';

// Añadir aquí todas las traducciones JavaScript
\$translations = [
    'locale_data' => [
        'messages' => [
            '' => [
                'domain' => 'messages',
                'plural-forms' => 'nplurals=2; plural=(n > 1);',
                'lang' => \$locale
            ],
            'Scanning…' => ['Analyse…'],
            'Re-scan' => ['Re-scanner'],
            // ... más traducciones
        ]
    ]
];

\$jsonFile = \$domain . '-' . \$locale . '-' . \$handle . '.json';
file_put_contents(\$jsonFile, json_encode(\$translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'JSON generado: ' . \$jsonFile . PHP_EOL;
"
```

### Opción 3: Herramientas Visuales

**Poedit** (recomendado para edición manual):
1. Descarga: https://poedit.net/
2. Abre el archivo `.po`
3. Edita traducciones
4. Guarda (genera automáticamente el `.mo`)

**Loco Translate** (plugin de WordPress):
1. Instala el plugin desde WordPress
2. Edita traducciones desde el dashboard
3. Exporta archivos `.po` y `.mo`

---

## 📝 Formato de Archivos JSON para JavaScript

WordPress espera archivos JSON en formato **Jed 1.1.x**:

```json
{
    "locale_data": {
        "messages": {
            "": {
                "domain": "messages",
                "plural-forms": "nplurals=2; plural=(n != 1);",
                "lang": "es_ES"
            },
            "Original String": ["Cadena Traducida"],
            "%d item": ["%d elemento", "%d elementos"]
        }
    }
}
```

**Nombre del archivo:**
```
{text-domain}-{locale}-{script-handle}.json
```

Ejemplo:
```
wp-ongoing-image-weight-tracker-es_ES-wpoiwt-admin.json
```

---

## 🔍 Verificación

### Comprobar que las traducciones funcionan:

1. **Cambiar idioma del sitio:**
   - Dashboard → Ajustes → Generales
   - Idioma del sitio: Español

2. **Limpiar caché:**
   ```php
   // Temporal en functions.php
   delete_option('wpoiwt_cached_translations');
   ```

3. **Recargar página del plugin:**
   - Las traducciones PHP deberían aparecer inmediatamente
   - Las traducciones JS pueden tardar 1-2 segundos en cargar

4. **Verificar en consola del navegador:**
   ```javascript
   console.log(wp.i18n.__('Re-scan', 'wp-ongoing-image-weight-tracker'));
   // Debería mostrar: "Re-escanear"
   ```

---

## 🚨 Solución de Problemas

### Las traducciones PHP no se muestran

✅ **Verificar archivo .mo:** Debe existir en `/languages` con permisos de lectura (644)

✅ **Idioma del sitio:** Dashboard → Ajustes → Generales → debe estar en "Español"

✅ **Timing de carga:** Asegúrate de que `load_plugin_textdomain()` se llama en el hook `init` con prioridad 0 (no en `plugins_loaded`)

✅ **Forzar recarga:** Desactiva y reactiva el plugin para recargar traducciones

✅ **Verificar locale:** Herramientas → Salud del sitio → Información → debe mostrar `es_ES`

### Las traducciones JavaScript no se muestran

✅ **Archivo JSON con hash:** El nombre debe incluir el hash MD5 del archivo JS:
```
wp-ongoing-image-weight-tracker-es_ES-[HASH]-wpoiwt-admin.json
```
Calcula el hash: `md5sum assets/admin.js`

✅ **Archivo JSON sin hash (fallback):**
```
wp-ongoing-image-weight-tracker-es_ES-wpoiwt-admin.json
```

✅ **Orden de carga:** `wp_set_script_translations()` debe llamarse DESPUÉS de `wp_enqueue_script()`

✅ **Dependencia wp-i18n:** El script debe incluir `'wp-i18n'` en el array de dependencias

✅ **Caché del navegador:** Recarga con `Ctrl+Shift+R` (forzar recarga completa)

### Archivo .mo corrupto o no válido

Si el archivo `.mo` no funciona, regénéralo usando el script `generate-mo.php`:

```bash
cd /wp-content/plugins/wp-ongoing-image-weight-tracker
php generate-mo.php
```

O usa este comando directo en el directorio `languages/`:

```bash
php -r "require '../generate-mo.php';"
```

### Pasos de activación completos

1. **Configurar idioma:** Dashboard → Ajustes → Generales → Español
2. **Guardar cambios**
3. **Desactivar plugin:** Plugins → Desactivar "Image Weight Tracker"
4. **Activar plugin:** Plugins → Activar "Image Weight Tracker"
5. **Limpiar caché:** Si usas plugin de caché (WP Rocket, etc.), vacía toda la caché
6. **Recargar navegador:** `Ctrl+Shift+R` en la página del plugin

### Script de prueba (functions.php temporal)

Para verificar que las traducciones se cargan correctamente:

```php
add_action('admin_notices', function() {
    if (!is_admin() || !current_user_can('manage_options')) return;
    
    $test = __('Re-scan', 'wp-ongoing-image-weight-tracker');
    $works = ($test === 'Re-escanear');
    
    echo '<div class="notice notice-' . ($works ? 'success' : 'error') . '">';
    echo '<p><strong>Test de traducción:</strong> ';
    echo $works ? '✓ FUNCIONANDO' : '✗ NO FUNCIONA';
    echo ' (Resultado: "' . esc_html($test) . '")</p>';
    echo '</div>';
});
```

### Verificar archivos necesarios

```bash
# En /languages/ deben existir:
✓ wp-ongoing-image-weight-tracker-es_ES.mo (1380 bytes aprox)
✓ wp-ongoing-image-weight-tracker-es_ES.po (3686 bytes aprox)
✓ wp-ongoing-image-weight-tracker-es_ES-[hash]-wpoiwt-admin.json
✓ wp-ongoing-image-weight-tracker-es_ES-wpoiwt-admin.json (fallback)
```

### Debug mode

Activa el modo debug en `wp-config.php` para ver errores:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Revisa `/wp-content/debug.log` para errores relacionados con traducciones.

---

## 📚 Referencias

- [WordPress i18n Documentation](https://developer.wordpress.org/plugins/internationalization/)
- [JavaScript Internationalization](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/)
- [Gettext Format](https://www.gnu.org/software/gettext/manual/html_node/PO-Files.html)
- [Jed Format (JSON)](http://messageformat.github.io/Jed/)
