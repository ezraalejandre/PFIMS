<?php
namespace App\Services;
class PlainTextInput
{
    public static function invalid(string $value): bool
    {
        if (!mb_check_encoding($value,'UTF-8')) return true;
        $value=html_entity_decode($value,ENT_QUOTES | ENT_HTML5,'UTF-8');
        return (bool)preg_match('/[<>`\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|(?:javascript|vbscript)\s*:|data\s*:\s*text\/html|\b(?:union\s+select|(?:sleep|benchmark|eval|alert)\s*\(|on(?:error|load|click)\s*=)|;\s*(?:drop|delete|insert|update|alter|exec)\b|[\'\"]\s*(?:or|and)\s+[\'\"\d][^\r\n]*[=<>]|^\s*=\s*[a-z]+\s*\(/iu',$value);
    }
}
