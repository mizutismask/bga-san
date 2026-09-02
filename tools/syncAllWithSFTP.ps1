#Requires -Version 5.1

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

$gameOptionsFile = if (Test-Path -LiteralPath (Join-Path $projectRoot 'gameoptions.json') -PathType Leaf) {
    'gameoptions.json'
} else {
    'gameoptions.jsonc'
}
$gamePreferencesFile = if (Test-Path -LiteralPath (Join-Path $projectRoot 'gamepreferences.json') -PathType Leaf) {
    'gamepreferences.json'
} else {
    'gamepreferences.jsonc'
}
$gameInfosFile = if (Test-Path -LiteralPath (Join-Path $projectRoot 'gameinfos.json') -PathType Leaf) {
    'gameinfos.json'
} else {
    'gameinfos.jsonc'
}
$gameStatsFile = if (Test-Path -LiteralPath (Join-Path $projectRoot 'stats.json') -PathType Leaf) {
    'stats.json'
} else {
    'stats.jsonc'
}

$selectedElements = @(
    'modules\php'
    'img'
    'dbmodel.sql'
    $gameOptionsFile
    $gamePreferencesFile
    $gameInfosFile
    $gameStatsFile
    'yourgamenamesk.css'
    'modules/js/Game.js'
)

if (-not (Get-Module -ListAvailable -Name Posh-SSH)) {
    throw 'Posh-SSH is required. Install it with: Install-Module Posh-SSH -Scope CurrentUser'
}

Import-Module Posh-SSH -ErrorAction Stop

$configPath = Join-Path $projectRoot '.vscode\sftp.json'
if (-not (Test-Path -LiteralPath $configPath -PathType Leaf)) {
    throw "SFTP configuration not found: $configPath"
}

$config = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
foreach ($property in 'host', 'port', 'username', 'password', 'remotePath') {
    if (-not $config.PSObject.Properties.Name.Contains($property) -or
        [string]::IsNullOrWhiteSpace([string] $config.$property)) {
        throw "Missing or empty '$property' in $configPath"
    }
}

$remoteRoot = ([string] $config.remotePath).TrimEnd('/', '\')
if ([string]::IsNullOrWhiteSpace($remoteRoot) -or $remoteRoot -eq '/') {
    throw "Unsafe remotePath in $configPath. Refusing to synchronize from the SFTP root."
}

# Validate every local source before deleting anything remotely.
$localItems = foreach ($relativePath in $selectedElements) {
    if ([IO.Path]::IsPathRooted($relativePath) -or $relativePath -match '(^|[\\/])\.\.([\\/]|$)') {
        throw "Unsafe selected path: $relativePath"
    }

    $localPath = Join-Path $projectRoot $relativePath
    if (-not (Test-Path -LiteralPath $localPath)) {
        throw "Local item not found: $localPath"
    }

    [pscustomobject]@{
        RelativePath = $relativePath
        Item = Get-Item -LiteralPath $localPath
    }
}

$securePassword = ConvertTo-SecureString ([string] $config.password) -AsPlainText -Force
$credential = [pscredential]::new([string] $config.username, $securePassword)
$session = $null

try {
    $session = New-SFTPSession `
        -ComputerName ([string] $config.host) `
        -Port ([int] $config.port) `
        -Credential $credential `
        -AcceptKey `
        -ErrorAction Stop

    Write-Host "Removing all existing content from $remoteRoot"
    $remoteChildren = @(Get-SFTPChildItem -SFTPSession $session -Path $remoteRoot -ErrorAction Stop)
    $remoteRootPrefix = $remoteRoot.TrimEnd('/') + '/'

    foreach ($remoteChild in $remoteChildren) {
        $remoteChildPath = ([string] $remoteChild.FullName).Replace('\', '/')
        if ([string]::IsNullOrWhiteSpace($remoteChildPath) -or
            -not $remoteChildPath.StartsWith($remoteRootPrefix, [StringComparison]::Ordinal) -or
            $remoteChildPath -eq $remoteRoot) {
            throw "Unsafe remote child path returned by the server: $remoteChildPath"
        }

        Write-Host "Removing $remoteChildPath"
        Remove-SFTPItem -SFTPSession $session -Path $remoteChildPath -Force -ErrorAction Stop
    }

    foreach ($localItem in $localItems) {
        Write-Host "Uploading $($localItem.Item.FullName) to $remoteRoot"
        Set-SFTPItem `
            -SFTPSession $session `
            -Path $localItem.Item.FullName `
            -Destination $remoteRoot `
            -Force `
            -ErrorAction Stop
    }

    Write-Host 'SFTP synchronization completed.'
} finally {
    if ($null -ne $session) {
        Remove-SFTPSession -SFTPSession $session -ErrorAction SilentlyContinue
    }
}
