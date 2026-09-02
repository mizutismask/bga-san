[CmdletBinding()]
param(
    [Parameter(Mandatory, Position = 0)]
    [ValidateScript({ Test-Path -LiteralPath $_ -PathType Container })]
    [string]$Directory
)

$ErrorActionPreference = "Stop"

$SourceDirectory = (Resolve-Path -LiteralPath $Directory).Path
$Extractor = Join-Path $PSScriptRoot "extract_corner_cards.py"
$Python = Get-Command python -ErrorAction Stop
$PdfFiles = @(Get-ChildItem -LiteralPath $SourceDirectory -File -Filter "*.pdf")

if ($PdfFiles.Count -eq 0) {
    throw "No PDF files found in '$SourceDirectory'."
}

foreach ($Pdf in $PdfFiles) {
    $OutputDirectory = Join-Path $SourceDirectory $Pdf.BaseName
    New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null

    & $Python.Source $Extractor $Pdf.FullName $OutputDirectory --no-prefix
    if ($LASTEXITCODE -ne 0) {
        throw "Image extraction failed for '$($Pdf.FullName)' with exit code $LASTEXITCODE."
    }
}
