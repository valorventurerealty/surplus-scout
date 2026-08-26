param(
    [string] $TaskName = 'VVR Surplus Scout Worker'
)

$ErrorActionPreference = 'Stop'
Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
Write-Output "Scheduled task '$TaskName' was stopped. It remains installed and will run at the next logon."
