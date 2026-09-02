$ErrorActionPreference = "Stop"

$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
$WorkRoot = "T:\games\San\worked\tokens\"
$RectoPdf = Join-Path $WorkRoot "PUNCHBOARD_SAN_RECTO_withdiecut"
$VersoPdf = Join-Path $WorkRoot "HC_SANCTUAIRES_PUNCHBOARD_VERSO_PRINT_DIECUT+ARTWORK_V2.pdf"
$RectoDir = Join-Path $WorkRoot "sanctuariesRecto"
$VersoDir = Join-Path $WorkRoot "sanctuariesVerso"
$MergedDir = Join-Path $WorkRoot "sanctuariesMerged"
$Output = Join-Path $RepoRoot "img\sanctuaryCards.png"

function Assert-EmptyDestination {
    param([string]$Path)

    if (Test-Path -LiteralPath $Path) {
        $ItemCount = (Get-ChildItem -LiteralPath $Path -Force | Measure-Object).Count
        if ($ItemCount -gt 0) {
            throw "Destination folder is not empty: $Path ($ItemCount item(s))"
        }
    }
}

Assert-EmptyDestination $RectoDir
Assert-EmptyDestination $VersoDir
Assert-EmptyDestination $MergedDir

python (Join-Path $RepoRoot "tools\clip_pdf_diecut.py") $RectoPdf $RectoDir --no-prefix
python (Join-Path $RepoRoot "tools\clip_pdf_diecut.py") $VersoPdf $VersoDir --no-prefix

New-Item -ItemType Directory -Force -Path $MergedDir | Out-Null
Get-ChildItem -Path $MergedDir -Filter "*.png" | Remove-Item -Force

$RectoFiles = Get-ChildItem -Path $RectoDir -Filter "*.png" | Sort-Object { [int]$_.BaseName }
$VersoFiles = Get-ChildItem -Path $VersoDir -Filter "*.png" | Sort-Object { [int]$_.BaseName }

for ($Index = 0; $Index -lt $RectoFiles.Count; $Index++) {
    Copy-Item -LiteralPath $RectoFiles[$Index].FullName -Destination (Join-Path $MergedDir ("{0:D2}.png" -f (($Index * 2) + 1)))
}

for ($Index = 0; $Index -lt $VersoFiles.Count; $Index++) {
    Copy-Item -LiteralPath $VersoFiles[$Index].FullName -Destination (Join-Path $MergedDir ("{0:D2}.png" -f (($Index * 2) + 2)))
}

$MontageInputs = Get-ChildItem -Path $MergedDir -Filter "*.png" | Sort-Object { [int]$_.BaseName } | ForEach-Object { $_.FullName }
if (Get-Command montage -ErrorAction SilentlyContinue) {
    montage $MontageInputs -tile 10 -geometry 300x+0+0 -gravity South -background none $Output
} elseif (Get-Command magick -ErrorAction SilentlyContinue) {
    magick montage $MontageInputs -tile 10 -geometry 300x516+0+0 -gravity South -background none $Output
} else {
    throw "ImageMagick is required: install it or add montage/magick to PATH."
}
if ($LASTEXITCODE -ne 0) {
    throw "ImageMagick montage failed with exit code $LASTEXITCODE."
}

if (Get-Command magick -ErrorAction SilentlyContinue) {
    $CompressedOutput = "$Output.compressed.png"
    magick $Output -strip -depth 8 -posterize 128 -define png:color-type=6 -define png:compression-level=9 -define png:compression-strategy=1 $CompressedOutput
    if ($LASTEXITCODE -ne 0) {
        throw "ImageMagick compression failed with exit code $LASTEXITCODE."
    }
    Move-Item -LiteralPath $CompressedOutput -Destination $Output -Force
}

$MaximumOutputSize = 5MB
$OutputSize = (Get-Item -LiteralPath $Output).Length
if ($OutputSize -ge $MaximumOutputSize) {
    throw "Generated sanctuaryCards.png is $OutputSize bytes; expected less than $MaximumOutputSize bytes."
}
