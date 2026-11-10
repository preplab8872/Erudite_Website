$ErrorActionPreference = 'Continue'
$ProgressPreference = 'SilentlyContinue'

Write-Host "`n=== ERUWEB WORK-PC TURNSTILE DIAGNOSTIC ===" -ForegroundColor Cyan
Write-Host "Run this from the Backend folder (the folder containing artisan)."

$backendPath = (Get-Location).Path
$reportPath = Join-Path $backendPath 'workpc-turnstile-diagnostic.txt'
$report = New-Object System.Collections.Generic.List[string]

function Add-ReportLine {
    param([string]$Text)
    $report.Add($Text)
    Write-Host $Text
}

function Section {
    param([string]$Title)
    Add-ReportLine ""
    Add-ReportLine "--- $Title ---"
}

function Read-EnvValue {
    param([string]$Name, [string]$Text)
    $pattern = '(?m)^\s*' + [regex]::Escape($Name) + '\s*=\s*(.*?)\s*$'
    $match = [regex]::Match($Text, $pattern)
    if (-not $match.Success) { return $null }
    return $match.Groups[1].Value.Trim().Trim('"').Trim("'")
}

function Run-And-Capture {
    param([string]$Label, [scriptblock]$Command)
    Add-ReportLine "[$Label]"
    try {
        $output = & $Command 2>&1 | Out-String
        if ([string]::IsNullOrWhiteSpace($output)) { $output = '(no output)' }
        foreach ($line in ($output.TrimEnd() -split "`r?`n")) {
            Add-ReportLine "  $line"
        }
    }
    catch {
        Add-ReportLine "  ERROR: $($_.Exception.Message)"
    }
}

Section 'Machine and project'
Add-ReportLine "Date: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss zzz')"
Add-ReportLine "Computer: $env:COMPUTERNAME"
Add-ReportLine "PowerShell: $($PSVersionTable.PSVersion)"
Add-ReportLine "Backend path: $backendPath"
Add-ReportLine "artisan present: $(Test-Path (Join-Path $backendPath 'artisan'))"
Add-ReportLine "vendor/autoload.php present: $(Test-Path (Join-Path $backendPath 'vendor/autoload.php'))"
Add-ReportLine "composer.lock present: $(Test-Path (Join-Path $backendPath 'composer.lock'))"
if (-not (Test-Path (Join-Path $backendPath 'artisan'))) {
    Add-ReportLine 'FATAL: This is not the Laravel Backend folder. Move to the folder containing artisan and rerun.'
    $report | Set-Content -Path $reportPath -Encoding UTF8
    Write-Host "`nReport saved to $reportPath" -ForegroundColor Yellow
    exit 2
}

Section 'PHP executable, version, extensions and TLS settings'
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
if ($phpCommand) {
    Add-ReportLine "PHP command: $($phpCommand.Source)"
    Run-And-Capture 'PHP version' { php -v | Select-Object -First 2 }
    Run-And-Capture 'PHP loaded configuration files' { php --ini }

    $phpInfoCode = @'
echo 'PHP_VERSION='.PHP_VERSION.PHP_EOL;
$extensions = ['curl','openssl','pdo','pdo_mysql','pdo_sqlite','mbstring','fileinfo','json','session','tokenizer','ctype','filter','hash','dom','xml'];
foreach ($extensions as $extension) {
    echo 'EXT_'.$extension.'='.(extension_loaded($extension) ? 'loaded' : 'MISSING').PHP_EOL;
}
echo 'CURL_CAINFO='.ini_get('curl.cainfo').PHP_EOL;
echo 'OPENSSL_CAFILE='.ini_get('openssl.cafile').PHP_EOL;
echo 'OPENSSL_CAPATH='.ini_get('openssl.capath').PHP_EOL;
echo 'ALLOW_URL_FOPEN='.ini_get('allow_url_fopen').PHP_EOL;
'@
    Run-And-Capture 'PHP extension and certificate configuration' { php -r $phpInfoCode }
} else {
    Add-ReportLine 'FATAL: php.exe is not on PATH.'
}

Section 'Composer and installed PHP packages'
$composerCommand = Get-Command composer -ErrorAction SilentlyContinue
if ($composerCommand) {
    Add-ReportLine "Composer command: $($composerCommand.Source)"
    Run-And-Capture 'Composer version' { composer --version }
    if (Test-Path (Join-Path $backendPath 'vendor/autoload.php')) {
        Run-And-Capture 'Composer platform requirements' { composer check-platform-reqs }
    } else {
        Add-ReportLine 'vendor/autoload.php is missing; run composer install after confirming PHP extensions.'
    }
} else {
    Add-ReportLine 'Composer is not installed or not on PATH.'
}

Section '.env settings (values are redacted)'
$envPath = Join-Path $backendPath '.env'
if (Test-Path $envPath) {
    $envText = [System.IO.File]::ReadAllText($envPath)
    foreach ($name in @('APP_KEY','TURNSTILE_SITE_KEY','TURNSTILE_SECRET_KEY','IP2LOCATION_API_KEY')) {
        $value = Read-EnvValue -Name $name -Text $envText
        $isSet = -not [string]::IsNullOrWhiteSpace($value)
        $lengthText = if ($isSet) { " (length $($value.Length))" } else { '' }
        Add-ReportLine "$name`: $(if ($isSet) {'SET'} else {'EMPTY or missing'})$lengthText"
    }
    foreach ($name in @('APP_ENV','APP_URL','DB_CONNECTION','SESSION_DRIVER','SESSION_SECURE_COOKIE','SESSION_DOMAIN','CACHE_STORE','QUEUE_CONNECTION')) {
        $value = Read-EnvValue -Name $name -Text $envText
        if ([string]::IsNullOrWhiteSpace($value)) { $value = '(not explicitly set; Laravel default may apply)' }
        Add-ReportLine "$name`: $value"
    }
    foreach ($name in @('CRM_DB_HOST','CRM_DB_DATABASE','CRM_DB_USERNAME','CRM_DB_PASSWORD')) {
        $value = Read-EnvValue -Name $name -Text $envText
        Add-ReportLine "$name`: $(if ([string]::IsNullOrWhiteSpace($value)) {'EMPTY or missing'} else {'SET'})"
    }
    $appUrl = Read-EnvValue -Name 'APP_URL' -Text $envText
    $secureCookie = Read-EnvValue -Name 'SESSION_SECURE_COOKIE' -Text $envText
    if ($appUrl -match '^http://' -and $secureCookie -match '^(?i:true|1|yes)$') {
        Add-ReportLine 'WARNING: SESSION_SECURE_COOKIE is true while APP_URL uses HTTP. The browser will not send the session cookie over plain HTTP.'
    }
} else {
    Add-ReportLine 'FATAL: .env is missing. .env.example is not a replacement for real Turnstile credentials.'
}

Section 'Laravel runtime directories and write access'
$directories = @(
    'bootstrap/cache',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs'
)
foreach ($relative in $directories) {
    $path = Join-Path $backendPath $relative
    if (-not (Test-Path $path)) {
        Add-ReportLine "$relative`: MISSING"
        continue
    }
    $testFile = Join-Path $path ('.workpc-write-test-' + [guid]::NewGuid().ToString('N') + '.tmp')
    try {
        [System.IO.File]::WriteAllText($testFile, 'test')
        Remove-Item $testFile -Force
        Add-ReportLine "$relative`: exists and writable"
    }
    catch {
        Add-ReportLine "$relative`: exists but NOT writable ($($_.Exception.Message))"
        if (Test-Path $testFile) { Remove-Item $testFile -Force -ErrorAction SilentlyContinue }
    }
}

Section 'Laravel routes and database migration status'
if (Test-Path (Join-Path $backendPath 'vendor/autoload.php')) {
    Run-And-Capture 'Security route registration' { php artisan route:list --path=security }
    Run-And-Capture 'Migration status (default connection)' { php artisan migrate:status }
} else {
    Add-ReportLine 'Skipped Artisan checks because vendor/autoload.php is missing.'
}

Section 'DNS and Windows HTTPS access to Cloudflare Turnstile'
Run-And-Capture 'DNS lookup' { Resolve-DnsName challenges.cloudflare.com -ErrorAction Stop | Select-Object -First 4 Name,Type,IPAddress }
$curlExe = Get-Command curl.exe -ErrorAction SilentlyContinue
if ($curlExe) {
    Run-And-Capture 'Windows curl HTTPS request to Turnstile JavaScript' {
        & $curlExe.Source -L -sS -o NUL -w 'HTTP=%{http_code}; TLS_VERIFY_RESULT=%{ssl_verify_result}; REMOTE_IP=%{remote_ip}\n' --connect-timeout 7 --max-time 15 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
        "curl_exit_code=$LASTEXITCODE"
    }
} else {
    Add-ReportLine 'curl.exe not found; Windows HTTPS probe skipped.'
}

Section 'PHP cURL HTTPS access to Cloudflare Turnstile'
if ($phpCommand) {
    $phpCurlProbe = @'
$url = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
if (!function_exists('curl_init')) { echo "PHP_CURL_EXTENSION=MISSING\n"; exit(2); }
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 7, CURLOPT_TIMEOUT => 15]);
$body = curl_exec($ch);
$error = curl_error($ch);
$errno = curl_errno($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$verify = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
curl_close($ch);
echo "PHP_CURL_HTTP_STATUS=$status\n";
echo "PHP_CURL_SSL_VERIFY_RESULT=$verify\n";
echo "PHP_CURL_ERROR_NUMBER=$errno\n";
echo "PHP_CURL_ERROR=".($error === '' ? '(none)' : $error)."\n";
echo "PHP_CURL_BODY_RECEIVED=".(is_string($body) && strlen($body) > 0 ? 'yes' : 'no')."\n";
if ($body === false) exit(3);
'@
    Run-And-Capture 'PHP cURL probe' { php -r $phpCurlProbe }
}

Section 'Proxy and clock basics'
Run-And-Capture 'Windows WinHTTP proxy configuration' { netsh winhttp show proxy }
Run-And-Capture 'Windows time synchronization status' { w32tm /query /status }

Section 'Next diagnostic evidence'
Add-ReportLine 'This report intentionally does not print APP_KEY, Turnstile keys, database passwords, IP2Location key, or cookie values.'
Add-ReportLine 'Open the browser security-check page and inspect DevTools > Console and Network for api.js, /security-check/consent, and /security-check/evaluate.'

$report | Set-Content -Path $reportPath -Encoding UTF8
Write-Host "`nDiagnostic complete. Report saved to:" -ForegroundColor Green
Write-Host $reportPath -ForegroundColor Yellow
Write-Host 'Send the diagnostic TXT file here. Do NOT send your .env file.' -ForegroundColor Cyan
