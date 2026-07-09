<?php
$code = file_get_contents('reportes_vehiculos.php');
$tokens = token_get_all($code);
$stack = [];
foreach ($tokens as $tok) {
    if (is_array($tok)) {
        $text = $tok[1];
        $line = $tok[2];
    } else {
        $text = $tok;
        $line = null;
    }
    if ($text === '{') {
        $stack[] = ['line' => $line, 'text' => $text];
    } elseif ($text === '}') {
        if (count($stack) === 0) {
            echo 'Unmatched closing } at line ' . ($line ?: 'unknown') . "\n";
        } else {
            array_pop($stack);
        }
    }
}
if (count($stack) > 0) {
    $last = end($stack);
    echo 'Unclosed { opened at line ' . $last['line'] . "\n";
} else {
    echo 'Braces balanced\n';
}
