<?php
/**
 * English strings (single language).
 */

/** @var array<string, string>|null */
$GLOBALS['_lang_strings'] = null;

function loadLangStrings(): array
{
    if ($GLOBALS['_lang_strings'] !== null) {
        return $GLOBALS['_lang_strings'];
    }
    $GLOBALS['_lang_strings'] = require __DIR__ . '/../lang/en.php';
    return $GLOBALS['_lang_strings'];
}

function __(string $key, array $replace = []): string
{
    $strings = loadLangStrings();
    $text = $strings[$key] ?? $key;
    foreach ($replace as $name => $value) {
        $text = str_replace(':' . $name, (string) $value, $text);
    }
    return $text;
}
