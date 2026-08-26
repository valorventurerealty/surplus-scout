param(
    [string] $TaskName = 'VVR Surplus Scout Worker'
)

$ErrorActionPreference = 'Stop'
$launcher = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot 'start-surplus-worker.ps1')).Path
$powerShell = (Get-Command powershell.exe -ErrorAction Stop).Source
$arguments = '-NoProfile -ExecutionPolicy Bypass -File "' + $launcher + '"'

$action = New-ScheduledTaskAction -Execute $powerShell -Argument $arguments
$trigger = New-ScheduledTaskTrigger -AtLogOn
$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -RestartCount 999 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit ([TimeSpan]::Zero)

Register-ScheduledTask `
    -TaskName $TaskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description 'Runs the isolated VVR Surplus Scout Laravel queue on this computer.' `
    -Force | Out-Null

Start-ScheduledTask -TaskName $TaskName
Write-Output "Scheduled task '$TaskName' was installed and started."
