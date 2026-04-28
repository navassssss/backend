<?php
/**
 * DHIC Portal — VAPID Key Generator (Pure PHP, No OpenSSL Config Required)
 *
 * Uses random_bytes() only — works on any PHP 7.2+ without openssl.cnf
 * The keys are generated as proper base64url-encoded values compatible
 * with the Web Push / VAPID specification.
 *
 * Run: php generate_vapid_keys.php
 */

// ── Pure PHP P-256 VAPID key generation ─────────────────────
// We generate a random 32-byte private key scalar and derive
// the public key using the P-256 curve via OpenSSL PEM import
// (which doesn't need openssl.cnf — only openssl_pkey_get_private does).

echo "\n🔑 DHIC Portal — VAPID Key Generator\n";
echo "─────────────────────────────────────────────────────\n\n";

if (!extension_loaded('openssl')) {
    die("❌ PHP OpenSSL extension is not loaded. Enable extension=openssl in php.ini\n");
}

// Generate 32 random bytes as private key scalar
$privateKeyBytes = random_bytes(32);

// Ensure scalar is in valid range for P-256:
// 1 <= d < n  where n = FFFFFFFF00000000FFFFFFFFFFFFFFFFBCE6FAADA7179E84F3B9CAC2FC632551
// Simply mask the top bit to keep it in range
$privateKeyBytes[0] = chr(ord($privateKeyBytes[0]) & 0x7F | 0x40); // ensure > 0

// Build a DER-encoded ECPrivateKey structure for P-256
// This allows us to load it via openssl_pkey_get_private WITHOUT needing openssl.cnf
$privateKeyDer = buildECPrivateKeyDer($privateKeyBytes);

// Load via PEM (this does NOT require openssl.cnf)
$privatePem = "-----BEGIN EC PRIVATE KEY-----\n" .
              chunk_split(base64_encode($privateKeyDer), 64, "\n") .
              "-----END EC PRIVATE KEY-----\n";

$privateKey = openssl_pkey_get_private($privatePem);

if (!$privateKey) {
    // OPENSSL_CONF workaround: try setting it to a temp dummy file
    $tmpConf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'openssl_dummy.cnf';
    if (!file_exists($tmpConf)) {
        file_put_contents($tmpConf, "[req]\ndistinguished_name=req\n[req_ext]\n");
    }
    putenv("OPENSSL_CONF={$tmpConf}");
    $privateKey = openssl_pkey_get_private($privatePem);
}

if (!$privateKey) {
    $err = openssl_error_string();
    echo "❌ Could not load EC private key: {$err}\n\n";
    echo "📋 MANUAL ALTERNATIVE — run in PowerShell or CMD:\n\n";
    printManualInstructions();
    exit(1);
}

$details = openssl_pkey_get_details($privateKey);

// Extract x, y coordinates of the public key
$x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
$y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

// Public key in uncompressed format: 0x04 || x || y
$publicKeyRaw = "\x04" . $x . $y;

// Private key scalar d (32 bytes)
$d = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);

// Base64url encode (no padding chars)
$publicKeyB64  = base64url($publicKeyRaw);
$privateKeyB64 = base64url($d);

// ── Output ───────────────────────────────────────────────────
echo "✅ VAPID Keys Generated Successfully!\n\n";
echo "PUBLIC KEY (add to both .env files):\n";
echo "  {$publicKeyB64}\n\n";
echo "PRIVATE KEY (backend .env only — keep secret!):\n";
echo "  {$privateKeyB64}\n\n";
echo "─────────────────────────────────────────────────────\n";
echo "📋 Add to  C:\\xampp\\acd\\.env\n";
echo "─────────────────────────────────────────────────────\n";
echo "VAPID_PUBLIC_KEY={$publicKeyB64}\n";
echo "VAPID_PRIVATE_KEY={$privateKeyB64}\n";
echo "VAPID_SUBJECT=mailto:admin@dhic.edu\n\n";
echo "─────────────────────────────────────────────────────\n";
echo "📋 Add to  C:\\xampp\\egovhasanath-main\\egovhasanath-main\\.env\n";
echo "─────────────────────────────────────────────────────\n";
echo "VITE_VAPID_PUBLIC_KEY={$publicKeyB64}\n\n";

// ── Helpers ───────────────────────────────────────────────────

function base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Build a minimal DER-encoded ECPrivateKey (RFC 5915) for P-256.
 * Structure:
 *   ECPrivateKey ::= SEQUENCE {
 *     version        INTEGER { ecPrivkeyVer1(1) } (1),
 *     privateKey     OCTET STRING,
 *     parameters [0] OID namedCurve (prime256v1)
 *   }
 */
function buildECPrivateKeyDer(string $d): string {
    $version    = "\x02\x01\x01";                     // INTEGER 1
    $privOctet  = "\x04\x20" . $d;                    // OCTET STRING, 32 bytes
    $oidP256    = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // OID prime256v1
    $params     = "\xa0\x0a" . $oidP256;              // [0] EXPLICIT OID

    $inner = $version . $privOctet . $params;
    return "\x30" . derLength(strlen($inner)) . $inner;
}

function derLength(int $len): string {
    if ($len < 0x80) return chr($len);
    $bytes = '';
    while ($len > 0) {
        $bytes = chr($len & 0xFF) . $bytes;
        $len >>= 8;
    }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function printManualInstructions(): void {
    echo "Option A — Node.js (if you have node installed):\n";
    echo "  node -e \"const {generateVAPIDKeys}=require('web-push');const k=generateVAPIDKeys();console.log('PUBLIC='+k.publicKey);console.log('PRIVATE='+k.privateKey);\"\n\n";
    echo "Option B — Run this URL in a browser to generate keys online:\n";
    echo "  https://vapidkeys.com\n\n";
    echo "Option C — PowerShell (uses .NET crypto):\n";
    echo "  (paste the PowerShell script below)\n\n";
    printPowerShellScript();
}

function printPowerShellScript(): void {
    echo <<<'PS'
# Run this in PowerShell to generate VAPID keys:
Add-Type -AssemblyName System.Security
$curve = [System.Security.Cryptography.ECCurve]::NamedCurves.nistP256
$ecdsa = [System.Security.Cryptography.ECDsa]::Create($curve)
$params = $ecdsa.ExportParameters($true)
$x = $params.Q.X; $y = $params.Q.Y; $d = $params.D
$pub = [Convert]::ToBase64String(([byte[]](0x04) + $x + $y)) -replace '\+','-' -replace '/','_' -replace '=',''
$priv = [Convert]::ToBase64String($d) -replace '\+','-' -replace '/','_' -replace '=',''
Write-Host "VAPID_PUBLIC_KEY=$pub"
Write-Host "VAPID_PRIVATE_KEY=$priv"

PS;
}
