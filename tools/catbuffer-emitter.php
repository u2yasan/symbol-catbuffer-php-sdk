#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Minimal Catbuffer-like PHP emitter (fixed-size fields only)
 *
 * Usage:
 *   php tools/catbuffer-emitter.php \
 *     --schema schemas/TransferTransactionV1.json \
 *     --namespace SymbolSdk\\Catbuffer \
 *     --out src/Catbuffer
 *
 * Supported scalar types:
 *   - u8, u16le, u32le, u64le (little-endian)
 *   - bytes(N)                (fixed length)
 *
 * Roadmap (extend later):
 *   - bytes(lenField) / varbytes
 *   - vectors (lenField + items)
 *   - nested structs
 */

function usage(): void {
    fwrite(STDERR, "Usage: php tools/catbuffer-emitter.php --schema=FILE --namespace=NS --out=DIR\n");
    exit(1);
}

$options = getopt('', ['schema:', 'namespace:', 'out:']);
$schemaFile = $options['schema'] ?? null;
$ns         = $options['namespace'] ?? null;
$outDir     = $options['out'] ?? null;

if (!$schemaFile || !$ns || !$outDir) usage();
if (!is_file($schemaFile)) {
    fwrite(STDERR, "schema not found: $schemaFile\n");
    exit(1);
}
if (!is_dir($outDir) && !mkdir($outDir, 0777, true)) {
    fwrite(STDERR, "cannot create out dir: $outDir\n");
    exit(1);
}

$schema = json_decode((string)file_get_contents($schemaFile), true);
if (!is_array($schema)) {
    fwrite(STDERR, "invalid schema json\n");
    exit(1);
}

$name    = $schema['name']    ?? null;
$version = (int)($schema['version'] ?? 1);
$fields  = $schema['fields']  ?? null;

if (!$name || !is_array($fields)) {
    fwrite(STDERR, "schema must have 'name' and array 'fields'\n");
    exit(1);
}

$className = $name . 'V' . $version;
$phpFile   = rtrim($outDir, '/').'/'.$className.'.php';

/* ---------- type helpers ---------- */

function normalizeType(string $t): string {
    $t = strtolower($t);
    // collapse spaces (allow "bytes( 64 )")
    $t = preg_replace('/\s+/', '', $t);
    return $t;
}
// --- bytes(lenField=messageSize) サポート ---
/**
 * bytes(lenField=X) のパターンを解析
 * @return array{ok:bool,lenField?:string}
 */
function parseBytesLenField(string $type): array {
    $type = normalizeType($type);
    if (preg_match('/^bytes\(lenfield=([a-z0-9_]+)\)$/', $type, $m)) {
        return ['ok' => true, 'lenField' => $m[1]];
    }
    return ['ok' => false];
}
// --- 可変長関連のメタを集める ---
$bytesLenRefs = []; // lenFieldName => dataFieldName
foreach (($schema['fields'] ?? []) as $f0) {
    $fname0 = (string)$f0['name'];
    $ftype0 = normalizeType((string)$f0['type']);
    $p = parseBytesLenField($ftype0);
    if ($p['ok']) {
        $bytesLenRefs[$p['lenField']] = $fname0; // 例: messageSize => message
    }
}

function phpDocType(string $type): string {
    $type = normalizeType($type);
    return match (true) {
        $type === 'u8',
        $type === 'u16le',
        $type === 'u32le',
        $type === 'u64le' => 'int',
        preg_match('/^bytes\(\d+\)$/', $type) === 1 => 'string',
        default => 'string',
    };
}

function sizeOfType(string $type): int {
    $type = normalizeType($type);
    return match (true) {
        $type === 'u8'     => 1,
        $type === 'u16le'  => 2,
        $type === 'u32le'  => 4,
        $type === 'u64le'  => 8,
        preg_match('/^bytes\((\d+)\)$/', $type) === 1
            => (int)preg_replace('/^bytes\((\d+)\)$/', '$1', $type),
        default => 0
    };
}

function genPackExpr(string $var, string $type): string {
    $type = normalizeType($type);
    switch ($type) {
        case 'u8':    return "pack('C', $var)";
        case 'u16le': return "pack('v', $var)";
        case 'u32le': return "pack('V', $var)";
        case 'u64le':
            // 64-bit LE: pack low then high
            return "(function(\$x){ \$lo=\$x & 0xFFFFFFFF; \$hi=(\$x >> 32) & 0xFFFFFFFF; return pack('V2', \$lo, \$hi);} )($var)";
        default:
            if (preg_match('/^bytes\((\d+)\)$/', $type, $m)) {
                $len = (int)$m[1];
                return "(function(\$s){ if (strlen(\$s)!==$len) throw new \\InvalidArgumentException('bytes($len) length mismatch'); return \$s; })($var)";
            }
            // unknown -> raw
            return $var;
    }
}

function genUnpackExpr(string $bufExpr, string $type): string {
    $type = normalizeType($type);
    switch ($type) {
        case 'u8':    return "ord($bufExpr)";
        case 'u16le': return "unpack('v', $bufExpr)[1]";
        case 'u32le': return "unpack('V', $bufExpr)[1]";
        case 'u64le':
            return "(function(\$b){ [\$lo,\$hi]=array_values(unpack('V2', \$b)); return (\$hi<<32)|\$lo; })($bufExpr)";
        default:
            if (preg_match('/^bytes\((\d+)\)$/', $type, $m)) {
                $len = (int)$m[1];
                return "(function(\$s){ if (strlen(\$s)!==$len) throw new \\InvalidArgumentException('bytes($len) length mismatch'); return \$s; })($bufExpr)";
            }
            return $bufExpr;
    }
}

/* ---------- emit ---------- */

$props = [];
$ctorParams = [];
$ctorAssign = [];
$serBody = [];
$deserBody = [];

$cursor = 0;
$offsetConsts = [];

foreach ($fields as $f) {
    if (!isset($f['name'], $f['type'])) {
        fwrite(STDERR, "each field must have name/type\n");
        exit(1);
    }
    $fname = (string)$f['name'];
    $ftype = normalizeType((string)$f['type']);

    // 可変長 bytes(lenField=...)
    $bytesLen = parseBytesLenField($ftype);     
    if ($bytesLen['ok']) {
        $lenField = $bytesLen['lenField'];       // 例: messageSize
        $dataField = $fname;                      // 例: message

        // プロパティ（data は string）
        $props[] = "    public string \$$dataField;";
        $ctorParams[] = "string \$$dataField";
        $ctorAssign[] = "        \$this->$dataField = \$$dataField;";

        // serialize: lenField の *値* を自動計算して先に書き出す
        // 注意：lenField 自身は schema にも別の field として存在する想定なので、
        // 「実フィールドとしての lenField を出力」する時に上書き値を使う必要がある。
        // → 簡単化のため：ここでは lenField 自体も *プロパティとして保持* し、serialize 時に上書き出力します。
        //    つまり lenField field 自体も別途、固定長 u16le 等として宣言されている前提。

        // 可変長本体の出力
        $serBody[] =
            "        // bytes(lenField=$lenField): \$this->$dataField\n" .
            "        \$__len_{$dataField} = strlen(\$this->$dataField);\n" .
            "        // lenField が直前／直後などどこにあっても良いように、実際の出力は lenField フィールド側で行う。\n" .
            "        \$out .= \$this->$dataField;";

        // deserialize: data の読み出しは「直前に lenField を既に読んでいる」想定が必要
        // ここでは「直前に lenField を読んだ」体で、カーソル変動をここで処理するのは難しいため、
        // *簡易方針*：lenField フィールド側でカーソル移動し、ここでは NOOP にする。
        // （= data は lenField 側のコードで読み取る）
        // よって、ここではデシリアライズのコードを追加しない（lenField 側に実装）。
        // 代わりにメタを使って lenField 側で処理する。
        // → ここは何も追加しない。

        // 可変長は「固定長サイズカウント」に入れない（後で size 再計算時に全体長から求める）
        continue;
    }

    $size  = sizeOfType($ftype);
    if ($size <= 0) {
        fwrite(STDERR, "unsupported/variable size field: {$fname}/{$f['type']} (extend emitter)\n");
        exit(1);
    }

    $phpType = phpDocType($ftype);
    $props[] = "    public {$phpType} \$$fname;";
    $ctorParams[] = "{$phpType} \$$fname";
    $ctorAssign[] = "        \$this->$fname = \$$fname;";

    $packVar = "\$this->$fname";
    if (isset($bytesLenRefs[$fname])) {
        $dataField = $bytesLenRefs[$fname];
        $deserBody[] = "        \$$dataField = substr(\$bin, $cursor, \$$fname);";
        $deserBody[] = "        if (strlen(\$$dataField) !== \$$fname) throw new \\InvalidArgumentException('invalid length for $dataField');";
        $cursor += 0; // 可変長分のカーソル加算が必要 → 可変なので式展開にする
        // カーソルを PHP で進めるため、$cursor の代わりに可変式へ置換:
        $deserBody[count($deserBody)-1] = "        \$__cur = " . $cursor . " + \$$fname;"; // 新しいカーソル
        $cursorExpr = "\$__cur"; // 以降この式を使う
    } else {
        // 固定長なら従来どおり $cursor を加算
        $cursor += $size;
    }
    $packExpr = genPackExpr($packVar, $ftype);
    $serBody[] = "        \$out .= $packExpr;";

    $deserBody[] = "        \$$fname = ".genUnpackExpr("substr(\$bin, $cursor, $size)", $ftype).";";

    // offsets for signature / signer public key if present
    if (in_array(strtolower($fname), ['signature'], true)) {
        $offsetConsts[] = "    public const SIGNATURE_OFFSET = $cursor;";
        $offsetConsts[] = "    public const SIGNATURE_SIZE   = $size;";
    }
    if (in_array(strtolower($fname), ['signerpublickey','signer'], true)) {
        $offsetConsts[] = "    public const SIGNER_OFFSET = $cursor;";
        $offsetConsts[] = "    public const SIGNER_SIZE   = $size;";
    }

    $cursor += $size;
}

$deserBody[] = "        return new self(" . implode(', ', array_map(fn($f)=>'$'.$f['name'], $fields)) . ");";

$sizeConst = "    public const SIZE = $cursor;";
$offsetBlock = $offsetConsts ? implode("\n", $offsetConsts) : '    // no well-known offsets';

$code = <<<PHP
<?php
declare(strict_types=1);

namespace $ns;

final class $className
{
$sizeConst
$offsetBlock

{PROPS}

    public function __construct(
{CTOR_PARAMS}
    ) {
{CTOR_ASSIGN}
    }

    /** @return string binary */
    public function serialize(): string
    {
        \$out = '';
{SER_BODY}
        return \$out;
    }

    /** @return self */
    public static function deserialize(string \$bin): self
    {
        if (strlen(\$bin) < self::SIZE) {
            throw new \\InvalidArgumentException('buffer too small: '.strlen(\$bin).' < '.self::SIZE);
        }
{DESER_BODY}
    }
}
PHP;

$code = str_replace('{PROPS}', implode("\n", $props), $code);
$code = str_replace('{CTOR_PARAMS}', '        ' . implode(",\n        ", $ctorParams), $code);
$code = str_replace('{CTOR_ASSIGN}', implode("\n", $ctorAssign), $code);
$code = str_replace('{SER_BODY}', implode("\n", $serBody), $code);
$code = str_replace('{DESER_BODY}', implode("\n", $deserBody), $code);

file_put_contents($phpFile, $code);
echo "generated: $phpFile\n";