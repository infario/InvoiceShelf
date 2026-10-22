<#
.SYNOPSIS
  Build a WhatsApp Share release locally for FTP upload to the server.

.DESCRIPTION
  Stops on the first failure. Steps:
    1. Preflight  - branch infario/2.4, no modified tracked files
    2. Verify     - WhatsApp + invoice tests, ESLint on the core dropdown, Pint
    3. Build      - module bundle (fails if committed dist is stale), host frontend
    4. Package    - build/releases/whatsapp-share-<Version>/ with:
                      server-upload-<Version>.zip  (Modules/WhatsappShare + public/build)
                      WhatsappShare-<Version>.zip  (module only)
                      invoice-dropdown-whatsapp.patch
                      SHA256SUMS.txt, RELEASE-NOTES.md

  Afterwards: FTP server-upload-<Version>.zip to the server, extract it at
  the app root, run `php artisan optimize:clear`, and smoke-test.
  See PATCH-UPLOADS.md.

.EXAMPLE
  .\release-local.ps1 -Version 0.1.0
  .\release-local.ps1 -Version 0.1.1 -BaseTag 2.4.8 -SkipTests
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$Version,

    [string]$BaseTag = '2.4.7',

    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
$Root = $PSScriptRoot
Set-Location $Root

# The 2.4.7 lock file needs PHP >= 8.4; XAMPP ships 8.2, so prefer the
# standalone PHP 8.4 when present.
$Php = if (Test-Path 'D:\php84\php.exe') { 'D:\php84\php.exe' } else { 'php' }

function Step([string]$Title) {
    Write-Host ""
    Write-Host "=== $Title ===" -ForegroundColor Cyan
}

function Run([string]$File, [string[]]$Arguments) {
    Write-Host "> $File $($Arguments -join ' ')" -ForegroundColor DarkGray
    & $File @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed with exit code ${LASTEXITCODE}: $File $($Arguments -join ' ')"
    }
}

# ---------------------------------------------------------------- preflight
Step 'Preflight'

$branch = (git branch --show-current).Trim()
if ($branch -ne 'infario/2.4') {
    throw "Expected branch 'infario/2.4', currently on '$branch'."
}

$dirty = git status --porcelain --untracked-files=no
if ($dirty) {
    throw "Modified tracked files present. Commit or stash them first:`n$dirty"
}

git rev-parse --verify --quiet "refs/tags/$BaseTag" | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw "Base tag '$BaseTag' not found locally. Run: git fetch upstream --tags"
}

Write-Host "Branch $branch, base tag $BaseTag, PHP: $Php"

# ---------------------------------------------------------------- verify
if (-not $SkipTests) {
    Step 'Verify'

    Run $Php @('artisan', 'test', '--compact', '--filter=WhatsappShareSettingsTest')
    Run $Php @('artisan', 'test', '--compact', 'tests/Feature/Admin/InvoiceTest.php')
    Run 'pnpm' @('exec', 'eslint', 'resources/scripts/admin/components/dropdowns/InvoiceIndexDropdown.vue', '--max-warnings', '0')
    Run $Php @('vendor/bin/pint', '--test', 'Modules/WhatsappShare/Http', 'Modules/WhatsappShare/Providers', 'tests/Feature/WhatsappShareSettingsTest.php')
} else {
    Step 'Verify (skipped)'
}

# ---------------------------------------------------------------- build
Step 'Build module bundle'

Run 'pnpm' @('exec', 'vite', 'build', '--config', 'Modules/WhatsappShare/vite.config.js')

$distDirty = git status --porcelain -- Modules/WhatsappShare/dist
if ($distDirty) {
    throw "Module dist changed after rebuild - commit the rebuilt dist first, then re-run:`n$distDirty"
}

Step 'Build host frontend'

Run 'pnpm' @('build')

# ---------------------------------------------------------------- package
Step 'Package'

$releaseName = "whatsapp-share-$Version"
$outDir = Join-Path $Root "build\releases\$releaseName"
if (Test-Path $outDir) {
    Remove-Item -Recurse -Force $outDir
}
New-Item -ItemType Directory -Force $outDir | Out-Null

$stage = Join-Path $outDir '_stage'
$serverStage = Join-Path $stage 'server'
$moduleStage = Join-Path $stage 'module'
New-Item -ItemType Directory -Force $serverStage, $moduleStage | Out-Null

# server-upload: module + rebuilt frontend, relative to the app root.
Copy-Item -Recurse 'Modules\WhatsappShare' (Join-Path $serverStage 'Modules\WhatsappShare')
New-Item -ItemType Directory -Force (Join-Path $serverStage 'public') | Out-Null
Copy-Item -Recurse 'public\build' (Join-Path $serverStage 'public\build')

# module-only zip: the WhatsappShare folder at the zip root.
Copy-Item -Recurse 'Modules\WhatsappShare' (Join-Path $moduleStage 'WhatsappShare')

$serverZip = Join-Path $outDir "server-upload-$Version.zip"
$moduleZip = Join-Path $outDir "WhatsappShare-$Version.zip"
Compress-Archive -Path (Join-Path $serverStage '*') -DestinationPath $serverZip
Compress-Archive -Path (Join-Path $moduleStage 'WhatsappShare') -DestinationPath $moduleZip

# Core patch, regenerated against the base tag so it is always current.
git diff $BaseTag -- resources/scripts/admin/components/dropdowns/InvoiceIndexDropdown.vue |
    Set-Content (Join-Path $outDir 'invoice-dropdown-whatsapp.patch')

# Release notes from commits touching the feature since the base tag.
$notes = @(
    "# WhatsApp Share $Version",
    "",
    "Base: InvoiceShelf $BaseTag (fork branch infario/2.4)",
    "",
    "## Changes"
)
$notes += (git log "$BaseTag..HEAD" --oneline -- Modules/WhatsappShare resources/scripts/admin/components/dropdowns/InvoiceIndexDropdown.vue | ForEach-Object { "- $_" })
$notes += @(
    "",
    "## Deploy (client.infario.com)",
    "1. FTP ``server-upload-$Version.zip`` to the server and extract at the app root (overwrites ``Modules/WhatsappShare`` and ``public/build``).",
    "2. Run ``php artisan optimize:clear``.",
    "3. Smoke test: invoice list dropdown shows 'Send on WhatsApp' and opens the correct wa.me chat; /admin/whatsapp-share loads; settings save."
)
$notes | Set-Content (Join-Path $outDir 'RELEASE-NOTES.md')

# Checksums for everything being uploaded/shared.
Get-ChildItem $outDir -File |
    Where-Object { $_.Name -ne 'SHA256SUMS.txt' } |
    Sort-Object Name |
    ForEach-Object { "$((Get-FileHash $_.FullName -Algorithm SHA256).Hash.ToLower())  $($_.Name)" } |
    Set-Content (Join-Path $outDir 'SHA256SUMS.txt')

Remove-Item -Recurse -Force $stage

Step 'Done'

Get-ChildItem $outDir -File |
    Sort-Object Name |
    ForEach-Object { "{0,-40} {1,10:N0} bytes" -f $_.Name, $_.Length } |
    Write-Host

Write-Host ""
Write-Host "Release artifacts in build\releases\$releaseName" -ForegroundColor Green
Write-Host "Next: FTP server-upload-$Version.zip to the server, extract at the app root,"
Write-Host "run 'php artisan optimize:clear', then smoke-test (see RELEASE-NOTES.md)."
