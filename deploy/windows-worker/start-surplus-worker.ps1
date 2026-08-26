param(
    [string] $ProjectPath = (Join-Path $PSScriptRoot '..\vvr-command-center-source')
)

$ErrorActionPreference = 'Stop'
$project = (Resolve-Path -LiteralPath $ProjectPath).Path
$artisan = Join-Path $project 'artisan'

if (-not (Test-Path -LiteralPath $artisan -PathType Leaf)) {
    throw "Laravel artisan was not found at $artisan"
}

Set-Location -LiteralPath $project

while ($true) {
    & php artisan queue:work database `
        --queue=surplus-research `
        --sleep=3 `
        --rest=1 `
        --tries=3 `
        --timeout=180 `
        --max-time=3600

    $exitCode = $LASTEXITCODE
    Write-Warning "Surplus Scout worker exited with code $exitCode. Restarting in 5 seconds."
    Start-Sleep -Seconds 5
}
