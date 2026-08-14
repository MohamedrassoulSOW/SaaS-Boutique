# Génère les pistes audio FR (Windows Hortense) puis assemble la vidéo MP4.
# Prérequis : ffmpeg dans le PATH, PHP GD, voix fr-FR Windows.

$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent $PSScriptRoot
$build = Join-Path $root 'tools\guide-video-build'
$slides = Join-Path $build 'slides'
$audio = Join-Path $build 'audio'
$outVideo = Join-Path $root 'public\videos\ndamstore-guide-nouveaux-utilisateurs.mp4'
$segments = Join-Path $build 'segments'
$listFile = Join-Path $build 'concat.txt'

New-Item -ItemType Directory -Force -Path $slides, $audio, $segments | Out-Null

$ffmpeg = $null
foreach ($cand in @(
    'ffmpeg',
    "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\ffmpeg-9.0-full_build\bin\ffmpeg.exe",
    'C:\ffmpeg\bin\ffmpeg.exe'
)) {
    if ($cand -eq 'ffmpeg') {
        $cmd = Get-Command ffmpeg -ErrorAction SilentlyContinue
        if ($cmd) { $ffmpeg = $cmd.Source; break }
    } elseif (Test-Path $cand) {
        $ffmpeg = $cand
        break
    }
}

if (-not $ffmpeg) {
    $found = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter ffmpeg.exe -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($found) { $ffmpeg = $found.FullName }
}

if (-not $ffmpeg) {
    throw 'ffmpeg introuvable. Réessayez après installation winget Gyan.FFmpeg.'
}

Write-Host "ffmpeg: $ffmpeg"

# 1) Slides
Write-Host '=== Slides PNG ==='
& php (Join-Path $root 'tools\build_guide_video_slides.php')
if ($LASTEXITCODE -ne 0) { throw 'Échec génération slides' }

$scripts = @(
    "Bonjour et bienvenue sur NdamStore, la plateforme qui simplifie la gestion de votre boutique. Ce guide vous montre, étape par étape, comment démarrer : catalogue, caisse, ventes, clients et bénéfices.",
    "Connectez-vous avec l'e-mail reçu à la création du compte. Si vous avez oublié votre mot de passe, utilisez le lien de réinitialisation : il reste valable trente minutes.",
    "Vérifiez le nom de l'entreprise active. Renseignez ensuite la fiscalité : T V A, mode H T ou T T C, et votre NINEA. Ces informations apparaîtront sur vos tickets et rapports.",
    "Dans Catalogue, créez vos produits avec le prix de vente et surtout le prix d'achat. Le prix d'achat est indispensable pour calculer des bénéfices fiables. Ajoutez aussi les catégories et, si besoin, le code-barres.",
    "Avant toute vente, ouvrez une session de caisse avec le fond de caisse du jour. Sans session ouverte, le point de vente reste bloqué. Le soir, clôturez pour contrôler les écarts.",
    "Ouvrez le point de vente. Ajoutez des articles par recherche ou scan de code-barres. Choisissez le mode de paiement : espèces, Wave, Orange Money ou crédit client. Validez, puis imprimez ou partagez le ticket.",
    "Créez une fiche client pour les ventes à crédit. Consultez le solde dû et enregistrez les paiements partiels. Vous gardez ainsi une vision claire des créances.",
    "Le menu Bénéfices calcule la marge : chiffre d'affaires moins le coût d'achat des quantités vendues. Filtrez par période ou par produit, puis exportez en C S V si besoin.",
    "Dans Équipe, invitez un caissier, un responsable ou un magasinier. Chaque rôle a des permissions adaptées. Le collaborateur reçoit un e-mail pour définir son mot de passe.",
    "Bravo : vous connaissez l'essentiel pour vendre et piloter votre boutique. Le guide écrit détaille chaque module. Pour toute aide, contactez le support NdamStore. Bonne réussite !"
)

# 2) Audio TTS
Write-Host '=== Audio TTS (fr-FR) ==='
Add-Type -AssemblyName System.Speech

for ($i = 0; $i -lt $scripts.Count; $i++) {
    $wav = Join-Path $audio ("narration-{0:D2}.wav" -f ($i + 1))
    $synth = New-Object System.Speech.Synthesis.SpeechSynthesizer
    try {
        $synth.SelectVoice('Microsoft Hortense Desktop')
    } catch {
        $synth.SelectVoiceByHints([System.Speech.Synthesis.VoiceGender]::Female, [System.Speech.Synthesis.VoiceAge]::Adult, 0, [System.Globalization.CultureInfo]::new('fr-FR'))
    }
    $synth.Rate = 0
    $synth.Volume = 100
    $synth.SetOutputToWaveFile($wav)
    $synth.Speak($scripts[$i])
    $synth.Dispose()
    Write-Host "OK $wav"
}

# 3) Segments slide+audio
Write-Host '=== Segments MP4 ==='
Remove-Item "$segments\*.mp4" -ErrorAction SilentlyContinue
$concatLines = @()

for ($i = 1; $i -le 10; $i++) {
    $n = '{0:D2}' -f $i
    $png = Join-Path $slides "slide-$n.png"
    $wav = Join-Path $audio "narration-$n.wav"
    $seg = Join-Path $segments "seg-$n.mp4"
    if (-not (Test-Path $png)) { throw "Slide manquante: $png" }
    if (-not (Test-Path $wav)) { throw "Audio manquant: $wav" }

    $ffmpegLog = Join-Path $build "ffmpeg-seg-$n.log"
    $p = Start-Process -FilePath $ffmpeg -ArgumentList @(
        '-y', '-loop', '1', '-i', $png, '-i', $wav,
        '-c:v', 'libx264', '-tune', 'stillimage', '-c:a', 'aac', '-b:a', '192k',
        '-pix_fmt', 'yuv420p', '-shortest',
        '-vf', 'scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2',
        $seg
    ) -Wait -PassThru -NoNewWindow -RedirectStandardError $ffmpegLog
    if ($p.ExitCode -ne 0) {
        Get-Content $ffmpegLog -Tail 30
        throw "ffmpeg segment $n échoué (code $($p.ExitCode))"
    }
    $concatLines += "file '$($seg.Replace('\','/'))'"
    Write-Host "OK $seg"
}

$concatLines | Set-Content -Path $listFile -Encoding ASCII

# 4) Concat
Write-Host '=== Assemblage final ==='
New-Item -ItemType Directory -Force -Path (Split-Path $outVideo) | Out-Null
$concatLog = Join-Path $build 'ffmpeg-concat.log'
$p = Start-Process -FilePath $ffmpeg -ArgumentList @(
    '-y', '-f', 'concat', '-safe', '0', '-i', $listFile, '-c', 'copy', $outVideo
) -Wait -PassThru -NoNewWindow -RedirectStandardError $concatLog
if ($p.ExitCode -ne 0) {
    $p = Start-Process -FilePath $ffmpeg -ArgumentList @(
        '-y', '-f', 'concat', '-safe', '0', '-i', $listFile,
        '-c:v', 'libx264', '-c:a', 'aac', '-pix_fmt', 'yuv420p', $outVideo
    ) -Wait -PassThru -NoNewWindow -RedirectStandardError $concatLog
}
if ($p.ExitCode -ne 0) {
    Get-Content $concatLog -Tail 40
    throw 'Assemblage final échoué'
}

$fi = Get-Item $outVideo
Write-Host ""
Write-Host "VIDEO OK : $($fi.FullName)"
Write-Host ("Taille  : {0:N1} Mo" -f ($fi.Length / 1MB))
