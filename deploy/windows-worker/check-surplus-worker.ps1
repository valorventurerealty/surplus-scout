param(
    [string] $ProjectPath = (Join-Path $PSScriptRoot '..\vvr-command-center-source')
)

$ErrorActionPreference = 'Stop'
$project = (Resolve-Path -LiteralPath $ProjectPath).Path
Set-Location -LiteralPath $project

& php artisan about --only=environment
if ($LASTEXITCODE -ne 0) { throw 'Laravel could not boot.' }

& php artisan tinker --execute='dump(["database" => \Illuminate\Support\Facades\DB::connection()->getPdo() !== null, "pending_scout_jobs" => \Illuminate\Support\Facades\DB::table("jobs")->where("queue", "surplus-research")->count(), "failed_scout_jobs" => \Illuminate\Support\Facades\DB::table("failed_jobs")->where("payload", "like", "%SurplusResearch%")->count()]);'
if ($LASTEXITCODE -ne 0) { throw 'The worker health check failed.' }
