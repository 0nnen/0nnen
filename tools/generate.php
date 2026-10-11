<?php
// ═══════════════════════════════════════════════════════════════
// generate.php — Génère les SVG animés du profil GitHub
// ───────────────────────────────────────────────────────────────
// Même identité que le portfolio mrebecca.net : crème #FEF8D4,
// vert encre #013E37, rouge REC #FF5B4D, polices Fredoka /
// JetBrains Mono (embarquées en base64 : GitHub affiche les SVG
// comme des images, sans accès aux polices externes).
//
// Usage (depuis la racine du dépôt 0nnen) :
//   php tools/generate.php [chemin/du/portfolio]
// Les projets viennent du portfolio (js/data.js, synchronisé avec
// la BDD par son tools/build.php) ; les vignettes de ses assets.
// Les animations sont en CSS dans chaque SVG (transform / opacity
// uniquement : fluides, et désactivées si l'OS demande moins de
// mouvement).
// ═══════════════════════════════════════════════════════════════

// PHP sans mbstring (Windows) : équivalents suffisants ici
if (!function_exists('mb_strlen')) {
    function mb_strlen($s) { return preg_match_all('/./us', $s); }
    function mb_strtoupper($s) { return strtoupper($s); }
}

$root = dirname(__DIR__);
$port = rtrim($argv[1] ?? dirname($root) . '/0nnen.github.io', '/\\');
$out  = "$root/assets";
if (!is_dir($out)) mkdir($out, 0755, true);

$INK = '#013E37'; $CREAM = '#FEF8D4'; $ACC = '#FF5B4D';
$SITE = 'https://mrebecca.net';

// ── Données du portfolio ──────────────────────────────────────
$js = file_get_contents("$port/js/data.js");
$i = strpos($js, '|| ');
$data = json_decode(substr($js, $i + 3, strrpos($js, '}') - $i - 2), true);
if (!$data) { fwrite(STDERR, "js/data.js du portfolio illisible\n"); exit(1); }

// ── Outils ────────────────────────────────────────────────────
function x($s) { return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

function fontFace(array $which): string {
    global $port;
    $files = [
        'Fredoka' => ['fredoka-normal-latin.woff2', '500 700'],
        'JetBrains Mono' => ['jetbrains-mono-normal-latin.woff2', '300 700'],
        'Hanken Grotesk' => ['hanken-grotesk-normal-latin.woff2', '300 700'],
    ];
    $css = '';
    foreach ($which as $f) {
        [$file, $w] = $files[$f];
        $css .= "@font-face{font-family:'$f';font-weight:$w;src:url(data:font/woff2;base64,"
              . base64_encode(file_get_contents("$port/assets/fonts/$file")) . ") format('woff2')}";
    }
    return $css;
}

/** Styles communs : polices, classes typo, keyframes partagées */
function baseStyle(array $fonts, string $extra = ''): string {
    global $INK, $CREAM, $ACC;
    return '<style>' . fontFace($fonts) . "
.d{font-family:Fredoka,'Arial Rounded MT Bold',sans-serif;font-weight:700}
.m{font-family:'JetBrains Mono',Consolas,monospace}
.b{font-family:'Hanken Grotesk','Segoe UI',sans-serif}
.ink{fill:$INK}.cream{fill:$CREAM}.acc{fill:$ACC}
@keyframes blink{0%,49%{opacity:1}50%,100%{opacity:0}}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.25}}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes rise{from{opacity:0;transform:translateY(60px)}to{opacity:1;transform:none}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes slideIn{from{opacity:0;transform:translateX(-40px)}to{opacity:1;transform:none}}
.blink{animation:blink 1.1s steps(1) infinite}
.pulse{animation:pulse 1.6s ease-in-out infinite}
$extra
@media (prefers-reduced-motion:reduce){*{animation:none!important}}
</style>";
}

function svg(string $name, int $w, int $h, string $title, string $body): void {
    global $out;
    $s = "<svg xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" width=\"$w\" height=\"$h\" viewBox=\"0 0 $w $h\" role=\"img\" aria-label=\"" . x($title) . "\">"
       . "<title>" . x($title) . "</title>$body</svg>\n";
    file_put_contents("$out/$name", $s);
    printf("%-28s %4d Ko\n", $name, strlen($s) / 1024);
}

/** Grille de fond (56 px, comme le site), optionnellement animée */
function grid(string $id, string $color, float $op, int $w, int $h, bool $anim = false): string {
    $p = "<defs><pattern id=\"$id\" width=\"56\" height=\"56\" patternUnits=\"userSpaceOnUse\">"
       . "<path d=\"M56 0H0V56\" fill=\"none\" stroke=\"$color\" stroke-opacity=\"$op\" stroke-width=\"1\"/></pattern></defs>";
    return $p . '<rect x="-56" y="-56" width="' . ($w + 112) . '" height="' . ($h + 112) . "\" fill=\"url(#$id)\"" . ($anim ? ' class="gridmove"' : '') . '/>';
}

/** Largeur d'un texte en JetBrains Mono (chasse fixe : 0,6 em) */
function monoW(string $s, float $size, float $ls = 0): float {
    $n = mb_strlen($s);
    return $n * $size * 0.6 + max(0, $n - 1) * $ls * $size;
}

/** Image locale → JPEG recadré en base64 (via ffmpeg) */
function thumb(string $rel, int $w, int $h): string {
    global $port;
    static $ff = null;
    if ($ff === null) {
        $ff = trim((string)shell_exec('where ffmpeg 2>NUL')) ?: '';
        $ff = strtok($ff, "\r\n") ?: '';
        if ($ff === '') {
            $g = glob(getenv('LOCALAPPDATA') . '/Microsoft/WinGet/Packages/Gyan.FFmpeg*/ffmpeg-*/bin/ffmpeg.exe');
            $ff = $g[0] ?? 'ffmpeg';
        }
    }
    $src = "$port/$rel";
    $webp = preg_replace('/\.(png|jpe?g)$/i', '.webp', $src);
    if (is_file($webp)) $src = $webp;
    $tmp = sys_get_temp_dir() . '/thumb_' . md5($rel . $w . $h) . '.jpg';
    $cmd = escapeshellarg($ff) . ' -v error -y -i ' . escapeshellarg($src)
         . " -vf \"scale=$w:$h:force_original_aspect_ratio=increase,crop=$w:$h\" -frames:v 1 -q:v 8 " . escapeshellarg($tmp);
    exec($cmd, $o, $rc);
    if ($rc !== 0 || !is_file($tmp)) { fwrite(STDERR, "vignette impossible : $rel\n"); return ''; }
    return 'data:image/jpeg;base64,' . base64_encode(file_get_contents($tmp));
}

/** Coupe un texte en lignes de N caractères max */
function wrap(string $s, int $n): array {
    return explode("\n", wordwrap($s, $n, "\n", true));
}

/** Couleurs d'un dégradé CSS du portfolio → stops SVG */
function gradStops(string $css): string {
    preg_match_all('/#[0-9a-fA-F]{6}(?:\s+(\d+)%)?/', $css, $m, PREG_SET_ORDER);
    if (!$m) return '<stop offset="0" stop-color="#0a5248"/><stop offset="1" stop-color="#02241f"/>';
    $n = count($m); $s = '';
    foreach ($m as $k => $c) {
        $off = isset($c[1]) && $c[1] !== '' ? $c[1] / 100 : ($n > 1 ? $k / ($n - 1) : 0);
        $s .= '<stop offset="' . $off . '" stop-color="' . substr($c[0], 0, 7) . '"/>';
    }
    return $s;
}

// ══════════════════════════════════════════════════════════════
// 1. EN-TÊTE — HUD + nom + machine à écrire + badge rotatif
// ══════════════════════════════════════════════════════════════
(function () use ($INK, $CREAM, $ACC) {
    $W = 1200; $H = 520;
    $tw = ['// Game Developer & Web', '// Builder of interactive worlds', '// C# · C++ · Unity · Unreal Engine · JS', '// Game Jams · Solo/Group projects · Freelance'];
    $per = 3.2; $tot = $per * count($tw);
    $css = ".gridmove{animation:grid 7s linear infinite}@keyframes grid{to{transform:translate(56px,56px)}}
.hud{animation:hud .7s cubic-bezier(.2,.8,.2,1) .1s both}@keyframes hud{from{transform:translateY(-60px)}to{transform:none}}
.r1{animation:rise .8s cubic-bezier(.2,.8,.2,1) .35s both}.r2{animation:rise .8s cubic-bezier(.2,.8,.2,1) .55s both}
.eb{animation:slideIn .7s ease .2s both}.ld{animation:fadeIn 1s ease 1s both}
.spin{transform-origin:1010px 250px;animation:spin 16s linear infinite}
.bob1{animation:bob 4s ease-in-out infinite}.bob2{animation:bob 5.5s ease-in-out .8s infinite}
.dia{transform-origin:150px 470px;animation:dia 9s ease-in-out infinite}@keyframes dia{0%,100%{transform:rotate(45deg)}50%{transform:translate(18px,-22px) rotate(70deg)}}
.scan{animation:scan 7s linear infinite}@keyframes scan{from{transform:translateY(-140px)}to{transform:translateY(560px)}}
.btn{animation:press 2.2s ease-in-out infinite}@keyframes press{0%,100%{transform:none}50%{transform:translate(-3px,-3px)}}
.sh{animation:shp 2.2s ease-in-out infinite}@keyframes shp{0%,100%{opacity:.35}50%{opacity:.6}}
";
    $twSvg = '';
    foreach ($tw as $k => $line) {
        $len = mb_strlen($line); $tw_w = monoW($line, 21);
        $a = $k * $per / $tot * 100; $b = ($k * $per + 1.4) / $tot * 100; $c = (($k + 1) * $per - 0.3) / $tot * 100; $e = ($k + 1) * $per / $tot * 100;
        $css .= ".tw$k{opacity:0;animation:tw$k {$tot}s linear infinite}@keyframes tw$k{0%{opacity:0}" . ($a > 0 ? sprintf('%.2f%%{opacity:0}', $a - 0.01) : '') . sprintf('%.2f%%{opacity:1}%.2f%%{opacity:1}%.2f%%{opacity:0}100%%{opacity:0}}', $a, $c, $e)
              . ".tc$k{animation:tc$k {$tot}s steps($len) infinite}@keyframes tc$k{0%{transform:scaleX(0)}" . ($a > 0 ? sprintf('%.2f%%{transform:scaleX(0)}', $a) : '') . sprintf('%.2f%%{transform:scaleX(1)}100%%{transform:scaleX(1)}}', $b)
              . ".cr$k{animation:cr$k {$tot}s steps($len) infinite}@keyframes cr$k{0%{transform:translateX(0)}" . ($a > 0 ? sprintf('%.2f%%{transform:translateX(0)}', $a) : '') . sprintf('%.2f%%{transform:translateX(%.1fpx)}100%%{transform:translateX(%.1fpx)}}', $b, $tw_w, $tw_w);
        $twSvg .= "<g class=\"tw$k\"><clipPath id=\"twc$k\"><rect class=\"tc$k\" x=\"72\" y=\"382\" width=\"" . ceil($tw_w + 4) . "\" height=\"34\" style=\"transform-origin:72px 0\"/></clipPath>"
                . "<text x=\"72\" y=\"408\" class=\"m ink\" font-size=\"21\" clip-path=\"url(#twc$k)\">" . x($line) . "</text>"
                . "<g class=\"cr$k\"><rect x=\"76\" y=\"387\" width=\"11\" height=\"26\" class=\"ink blink\"/></g></g>";
    }

    $nav = ['BOSS', 'LEVELS', 'PLAYER', 'INVENTORY', 'CONTACT'];
    $hud = "<g class=\"hud\"><rect width=\"$W\" height=\"46\" fill=\"$CREAM\"/><path d=\"M0 46H$W\" stroke=\"$INK\" stroke-width=\"3\"/>"
         . "<rect x=\"20\" y=\"18\" width=\"10\" height=\"10\" class=\"ink pulse\"/><text x=\"40\" y=\"28\" class=\"m ink\" font-size=\"13\" font-weight=\"700\" letter-spacing=\"1\">MATHIAS.R</text>"
         . "<path d=\"M160 0V46\" stroke=\"$INK\" stroke-width=\"2\"/>";
    $x = 560;
    foreach ($nav as $k => $n) {
        $w = monoW($n, 12, .06) + 34;
        $act = $k === 1;
        $hud .= "<path d=\"M$x 0V46\" stroke=\"$INK\" stroke-width=\"2\"/>" . ($act ? "<rect x=\"$x\" y=\"0\" width=\"$w\" height=\"45\" fill=\"$INK\"/>" : '')
              . '<text x="' . ($x + 17) . '" y="28" class="m ' . ($act ? 'cream' : 'ink') . "\" font-size=\"12\" letter-spacing=\".7\">$n</text>";
        $x += $w;
    }
    $hud .= "<path d=\"M$x 0V46\" stroke=\"$INK\" stroke-width=\"2\"/><text x=\"" . ($x + 16) . "\" y=\"28\" class=\"m ink\" font-size=\"12\" letter-spacing=\"1.2\">SCORE <tspan font-weight=\"700\">100%</tspan></text>";
    $x += 140;
    $hud .= "<path d=\"M$x 0V46\" stroke=\"$INK\" stroke-width=\"2\"/><rect x=\"$x\" y=\"0\" width=\"" . ($W - $x) . "\" height=\"45\" fill=\"$INK\"/><text x=\"" . ($x + ($W - $x) / 2) . "\" y=\"28\" text-anchor=\"middle\" class=\"m cream\" font-size=\"12\" font-weight=\"700\">EN</text></g>";

    $badge = "<g transform=\"translate(1010 250)\"><circle r=\"118\" fill=\"$CREAM\" stroke=\"$INK\" stroke-width=\"3\"/></g>"
           . "<g class=\"spin\"><defs><path id=\"circ\" d=\"M1010 250m-96 0a96 96 0 1 1 192 0a96 96 0 1 1-192 0\"/></defs>"
           . "<text class=\"m ink\" font-size=\"15.5\" font-weight=\"700\" textLength=\"598\" lengthAdjust=\"spacing\"><textPath href=\"#circ\" xlink:href=\"#circ\">UNITY · UNREAL · C# · C++ · WEB · GAME JAMS · </textPath></text></g>"
           . "<g transform=\"translate(1010 250) rotate(-6)\"><rect x=\"-52\" y=\"-52\" width=\"104\" height=\"104\" fill=\"$INK\"/><text y=\"20\" text-anchor=\"middle\" class=\"d cream\" font-size=\"54\">MR</text></g>";

    $body = baseStyle(['Fredoka', 'JetBrains Mono', 'Hanken Grotesk'], $css)
        . "<rect width=\"$W\" height=\"$H\" fill=\"$CREAM\"/>"
        . '<g>' . grid('g', $INK, .07, $W, $H, true) . '</g>'
        . "<defs><linearGradient id=\"sl\" x1=\"0\" y1=\"0\" x2=\"0\" y2=\"1\"><stop offset=\"0\" stop-color=\"$INK\" stop-opacity=\"0\"/><stop offset=\".5\" stop-color=\"$INK\" stop-opacity=\".06\"/><stop offset=\"1\" stop-color=\"$INK\" stop-opacity=\"0\"/></linearGradient></defs>"
        . "<rect class=\"scan\" width=\"$W\" height=\"130\" fill=\"url(#sl)\"/>"
        // décor flottant
        . "<g class=\"bob1\"><rect x=\"760\" y=\"92\" width=\"46\" height=\"46\" fill=\"none\" stroke=\"$INK\" stroke-width=\"3\"/><rect x=\"771\" y=\"103\" width=\"24\" height=\"24\" class=\"ink\"/></g>"
        . "<g class=\"bob2\"><rect x=\"1130\" y=\"420\" width=\"34\" height=\"34\" class=\"acc\"/></g>"
        . "<rect class=\"dia\" x=\"880\" y=\"440\" width=\"30\" height=\"30\" fill=\"none\" stroke=\"$INK\" stroke-width=\"3\" style=\"transform-origin:895px 455px\"/>"
        . "<text x=\"820\" y=\"470\" class=\"m ink\" font-size=\"18\" opacity=\".35\" letter-spacing=\"4\">+ + +</text>"
        . "<text x=\"1110\" y=\"100\" class=\"m ink\" font-size=\"16\" opacity=\".35\" letter-spacing=\"4\">▲ ▲</text>"
        . $hud
        // titre
        . "<g class=\"eb\"><rect x=\"72\" y=\"104\" width=\"12\" height=\"12\" class=\"ink pulse\"/><text x=\"98\" y=\"116\" class=\"m ink\" font-size=\"15\" letter-spacing=\"3.2\">GAME &amp; WEB DEVELOPER - FRANCE</text></g>"
        . "<g class=\"r1\"><text x=\"64\" y=\"238\" class=\"d ink\" font-size=\"124\" letter-spacing=\"-1.5\">MATHIAS</text></g>"
        . "<g class=\"r2\"><text x=\"64\" y=\"348\" class=\"d\" font-size=\"124\" letter-spacing=\"-1.5\" fill=\"none\" stroke=\"$INK\" stroke-width=\"3\">REBECCA</text></g>"
        . $twSvg
        // bouton PRESS START
        . "<g class=\"ld\"><g class=\"sh\"><rect x=\"80\" y=\"446\" width=\"220\" height=\"52\" fill=\"$INK\"/></g>"
        . "<g class=\"btn\"><rect x=\"72\" y=\"438\" width=\"220\" height=\"52\" fill=\"$INK\" stroke=\"$INK\" stroke-width=\"2\"/><text x=\"182\" y=\"470\" text-anchor=\"middle\" class=\"m cream\" font-size=\"14\" font-weight=\"700\" letter-spacing=\"1.5\">▶ PRESS START</text></g>"
        . "<rect x=\"312\" y=\"438\" width=\"190\" height=\"52\" fill=\"$CREAM\" stroke=\"$INK\" stroke-width=\"2\"/><text x=\"407\" y=\"470\" text-anchor=\"middle\" class=\"m ink\" font-size=\"14\" font-weight=\"700\" letter-spacing=\"1.5\">CONTACT ME</text></g>"
        . $badge
        . "<g class=\"ld\"><rect x=\"930\" y=\"462\" width=\"230\" height=\"34\" fill=\"$INK\"/><text x=\"1045\" y=\"484\" text-anchor=\"middle\" class=\"m cream\" font-size=\"12\" font-weight=\"700\" letter-spacing=\"1.4\">ZONE 01/06 - START</text></g>"
        . "<rect x=\"1.5\" y=\"1.5\" width=\"" . ($W - 3) . "\" height=\"" . ($H - 3) . "\" fill=\"none\" stroke=\"$INK\" stroke-width=\"3\"/>";
    svg('header.svg', $W, $H, 'Mathias REBECCA - Game & Web Developer', $body);
})();

// ══════════════════════════════════════════════════════════════
// 2. BANDEAUX DÉFILANTS (marquee)
// ══════════════════════════════════════════════════════════════
function marquee(string $name, string $text, bool $rev, string $bg, string $fg, float $speed) {
    $W = 1200; $H = 58;
    $L = (int)max(1300, ceil(mb_strlen($text) * 26 * 0.56)); // largeur d'une copie du texte
    $css = ".mq{animation:mq {$speed}s linear infinite}@keyframes mq{" . ($rev ? "from{transform:translateX(-{$L}px)}to{transform:none}" : "to{transform:translateX(-{$L}px)}") . '}';
    $t = '';
    foreach ([0, $L, 2 * $L] as $x) $t .= "<text x=\"$x\" y=\"39\" class=\"d\" fill=\"$fg\" font-size=\"26\" textLength=\"$L\" lengthAdjust=\"spacing\">" . x($text) . '</text>';
    svg($name, $W, $H, 'Marquee', baseStyle(['Fredoka'], $css) . "<rect width=\"$W\" height=\"$H\" fill=\"$bg\"/><g class=\"mq\">$t</g>"
        . "<path d=\"M0 1.5H$W M0 " . ($H - 1.5) . "H$W\" stroke=\"$fg\" stroke-width=\"3\"/>");
}
marquee('marquee-skills.svg', 'Unity ★ Unreal Engine ★ C# ★ C++ ★ Game Design ★ Level Design ★ React ★ WebGL ★ Game Jams ★ ', false, $INK, $CREAM, 26);
$names = array_merge([$data['VEDETTE']['title']], array_column($data['PROJECTS'], 'title'));
marquee('marquee-levels.svg', implode(' ★ ', $names) . ' ★ ', true, $CREAM, $INK, 30);

// ══════════════════════════════════════════════════════════════
// 3. BOSS — Etheria's End
// ══════════════════════════════════════════════════════════════
(function () use ($INK, $CREAM, $ACC, $data) {
    $W = 1200; $H = 600;
    $img = thumb('assets/projects/EtheriasEnd/DevLog-Environment_1.poster.jpg', 840, 480);
    $css = ".ghost{animation:bob 6s ease-in-out infinite}.ring{transform-origin:1080px 110px;animation:spin 24s linear infinite}
.t1{animation:rise .8s cubic-bezier(.2,.8,.2,1) .2s both}.fr{animation:slideIn .8s ease .4s both}.rt{animation:fadeIn 1s ease .7s both}
.cta{animation:press 2s ease-in-out infinite}@keyframes press{0%,100%{transform:none}50%{transform:translate(-3px,-3px)}}
.warn{animation:pulse 1.2s ease-in-out infinite}";
    $desc = ['A 3rd-person adventure on floating islands.', 'Play as Kael to rescue childhood friend Auria', 'from a cult. Exploration, puzzles and combat:', 'curiosity → tension → adrenaline → pride.', 'Cross the archipelagos on foot, by glider', 'or grappling hook.'];
    $d = '';
    foreach ($desc as $k => $l) $d .= '<text x="650" y="' . (300 + $k * 27) . '" class="b cream" font-size="18">' . x($l) . '</text>';
    $specs = [['ROLE', 'LEAD DEV · GAME DESIGN'], ['ENGINE', 'UNREAL ENGINE'], ['SYSTEMS', 'COMBAT · QUESTS · DIALOGUE'], ['STATUS', '■ IN DEV']];
    $s = '';
    foreach ($specs as $k => [$a, $b]) {
        $cx = 650 + ($k % 2) * 262; $cy = 466 + intdiv($k, 2) * 56;
        $s .= "<rect x=\"$cx\" y=\"$cy\" width=\"250\" height=\"48\" fill=\"none\" stroke=\"$CREAM\" stroke-opacity=\".4\" stroke-width=\"2\"/>"
            . '<text x="' . ($cx + 14) . '" y="' . ($cy + 19) . "\" class=\"m cream\" font-size=\"10\" letter-spacing=\"2\" opacity=\".7\">$a</text>"
            . '<text x="' . ($cx + 14) . '" y="' . ($cy + 37) . '" class="m cream" font-size="12" font-weight="700" letter-spacing=".6"' . '>' . x($b) . '</text>';
    }
    $body = baseStyle(['Fredoka', 'JetBrains Mono', 'Hanken Grotesk'], $css)
        . "<rect width=\"$W\" height=\"$H\" fill=\"$INK\"/>" . grid('gb', $CREAM, .06, $W, $H)
        . "<g class=\"ghost\"><text x=\"1190\" y=\"590\" text-anchor=\"end\" class=\"d\" font-size=\"300\" fill=\"none\" stroke=\"$CREAM\" stroke-opacity=\".08\" stroke-width=\"3\">BOSS</text></g>"
        . "<g class=\"ring\"><circle cx=\"1080\" cy=\"110\" r=\"60\" fill=\"none\" stroke=\"$CREAM\" stroke-opacity=\".25\" stroke-width=\"3\" stroke-dasharray=\"14 10\"/></g>"
        // en-tête
        . "<text x=\"48\" y=\"56\" class=\"m acc warn\" font-size=\"14\" font-weight=\"700\" letter-spacing=\"2\">⚠ BOSS LEVEL</text>"
        . "<text x=\"200\" y=\"56\" class=\"m cream\" font-size=\"12\" letter-spacing=\"2\" opacity=\".7\">FEATURED PROJECT - LEAD DEV &amp; GAME DESIGNER</text>"
        . "<path d=\"M640 51H930\" stroke=\"$CREAM\" stroke-opacity=\".3\" stroke-width=\"2\"/><text x=\"948\" y=\"56\" class=\"m cream\" font-size=\"12\" letter-spacing=\"2\" opacity=\".7\">STEAM · JUNE 2026</text>"
        . "<g class=\"t1\"><text x=\"44\" y=\"170\" class=\"d cream\" font-size=\"110\" letter-spacing=\"-1\">ETHERIA'S</text>"
        . "<text x=\"640\" y=\"170\" class=\"d\" font-size=\"110\" letter-spacing=\"-1\" fill=\"none\" stroke=\"$CREAM\" stroke-width=\"3\">END</text></g>"
        // cadre vidéo
        . "<g class=\"fr\"><rect x=\"58\" y=\"222\" width=\"560\" height=\"350\" fill=\"$CREAM\" opacity=\".18\"/>"
        . "<rect x=\"48\" y=\"212\" width=\"560\" height=\"350\" fill=\"#02241f\" stroke=\"$CREAM\" stroke-width=\"2\"/>"
        . "<path d=\"M48 242H608\" stroke=\"$CREAM\" stroke-width=\"2\"/><circle cx=\"64\" cy=\"227\" r=\"4\" class=\"acc pulse\"/>"
        . "<text x=\"76\" y=\"231\" class=\"m cream\" font-size=\"10\" letter-spacing=\"2\">REC · DEVLOG_04.MP4</text><text x=\"594\" y=\"231\" text-anchor=\"end\" class=\"m cream\" font-size=\"10\" letter-spacing=\"2\">DAY/NIGHT</text>"
        . "<image x=\"49\" y=\"243\" width=\"558\" height=\"318\" preserveAspectRatio=\"xMidYMid slice\" href=\"$img\" xlink:href=\"$img\"/>"
        . "<rect x=\"60\" y=\"526\" width=\"190\" height=\"24\" fill=\"$INK\"/><text x=\"155\" y=\"542\" text-anchor=\"middle\" class=\"m cream\" font-size=\"10\" letter-spacing=\"1.5\">IN DEVELOPMENT · 2026</text></g>"
        . "<g class=\"rt\">$d$s"
        . '</g>'
        . "<g class=\"cta\"><rect x=\"910\" y=\"212\" width=\"240\" height=\"50\" fill=\"$CREAM\"/><text x=\"1030\" y=\"243\" text-anchor=\"middle\" class=\"m ink\" font-size=\"14\" font-weight=\"700\" letter-spacing=\"1.5\">FIGHT THE BOSS →</text></g>"
        . "<rect x=\"1.5\" y=\"1.5\" width=\"" . ($W - 3) . "\" height=\"" . ($H - 3) . "\" fill=\"none\" stroke=\"$INK\" stroke-width=\"3\"/>";
    svg('boss.svg', $W, $H, "Etheria's End - Boss level", $body);
})();

// ══════════════════════════════════════════════════════════════
// 4. TITRES DE SECTION
// ══════════════════════════════════════════════════════════════
function sectionTitle(string $name, string $zone, string $label, string $w1, string $w2, bool $dark) {
    global $INK, $CREAM;
    $W = 1200; $H = 190;
    $bg = $dark ? $INK : $CREAM; $fg = $dark ? $CREAM : $INK;
    $css = '.t{animation:rise .8s cubic-bezier(.2,.8,.2,1) .15s both}.k{animation:slideIn .6s ease both}';
    $body = baseStyle(['Fredoka', 'JetBrains Mono'], $css)
        . "<rect width=\"$W\" height=\"$H\" fill=\"$bg\"/>" . grid('gt' . md5($name), $fg, .07, $W, $H)
        . "<g class=\"k\"><rect x=\"48\" y=\"30\" width=\"" . (monoW($zone, 12, .2) + 26) . "\" height=\"28\" fill=\"$fg\"/>"
        . "<text x=\"61\" y=\"49\" font-size=\"12\" font-weight=\"700\" letter-spacing=\"2.4\" class=\"m\" fill=\"$bg\">$zone</text>"
        . '<text x="' . (monoW($zone, 12, .2) + 92) . "\" y=\"49\" class=\"m\" fill=\"$fg\" font-size=\"12\" letter-spacing=\"2.4\">" . x($label) . '</text></g>'
        . "<g class=\"t\"><text x=\"44\" y=\"158\" class=\"d\" font-size=\"96\" letter-spacing=\"-1\" fill=\"$fg\">" . x($w1) . " <tspan fill=\"none\" stroke=\"$fg\" stroke-width=\"2.5\">" . x($w2) . '</tspan></text></g>';
    svg($name, $W, $H, "$w1 $w2", $body);
}
sectionTitle('title-levels.svg', 'ZONE 02', 'LEVEL SELECT', 'MY', 'PROJECTS', false);

// ══════════════════════════════════════════════════════════════
// 5. CARTES DE NIVEAU (une par projet, chacune liée à sa page)
// ══════════════════════════════════════════════════════════════
$rots = [-0.8, 0.9, -1.1, 0.7, -0.7, 1];
$cards = [];
foreach ($data['PROJECTS'] as $k => $p) {
    $W = 420; $H = 384;
    $cx = 14; $cy = 14; $cw = 388; $ch = 350;  // carte, marge pour rotation + ombre
    $rot = $rots[$k % count($rots)];
    $num = str_pad((string)($k + 1), 2, '0', STR_PAD_LEFT);
    $img = !empty($p['img']) ? thumb($p['img'], 620, 268) : '';
    $id = 'c' . $k;
    $css = ".card{transform-origin:210px 186px;animation:cardIn .7s cubic-bezier(.2,.8,.2,1) both,float 6s ease-in-out .7s infinite}"
         . "@keyframes cardIn{from{opacity:0;transform:translateY(30px) rotate({$rot}deg)}to{opacity:1;transform:rotate({$rot}deg)}}"
         . "@keyframes float{0%,100%{transform:rotate({$rot}deg)}50%{transform:translateY(-6px) rotate(" . ($rot * -0.4) . "deg)}}"
         . ".enter{animation:nudge 1.4s ease-in-out infinite}@keyframes nudge{0%,100%{transform:none}50%{transform:translateX(4px)}}";
    $pills = ''; $px = $cx + 16;
    foreach (array_slice($p['pills'], 0, 4) as $pl) {
        $pw = monoW($pl, 10) + 16;
        if ($px + $pw > $cx + $cw - 16) break;
        $pills .= "<rect x=\"$px\" y=\"" . ($cy + 284) . "\" width=\"$pw\" height=\"22\" fill=\"none\" stroke=\"$INK\" stroke-opacity=\".45\" stroke-width=\"1.5\"/>"
                . '<text x="' . ($px + 8) . '" y="' . ($cy + 299) . '" class="m ink" font-size="10">' . x($pl) . '</text>';
        $px += $pw + 7;
    }
    $desc = '';
    foreach (array_slice(wrap($p['descEn'], 50), 0, 2) as $j => $l) $desc .= '<text x="' . ($cx + 16) . '" y="' . ($cy + 254 + $j * 18) . '" class="m ink" font-size="11.5" opacity=".8">' . x($l) . '</text>';
    $tag = $p['tagEn'] ?? $p['tag'];
    $title = mb_strtoupper($p['title']);
    $fs = mb_strlen($title) > 16 ? 23 : 27;
    $body = baseStyle(['Fredoka', 'JetBrains Mono'], $css) // 2 polices : SVG plus léger
        . "<defs><linearGradient id=\"gr$id\" x1=\"0\" y1=\"0\" x2=\"1\" y2=\"1\">" . gradStops($p['grad']) . "</linearGradient>"
        . "<clipPath id=\"im$id\"><rect x=\"" . ($cx + 1) . '" y="' . ($cy + 31) . '" width="' . ($cw - 2) . "\" height=\"168\"/></clipPath></defs>"
        . '<g class="card">'
        . '<rect x="' . ($cx + 6) . '" y="' . ($cy + 6) . "\" width=\"$cw\" height=\"$ch\" fill=\"$INK\" opacity=\".22\"/>"
        . "<rect x=\"$cx\" y=\"$cy\" width=\"$cw\" height=\"$ch\" fill=\"$CREAM\" stroke=\"$INK\" stroke-width=\"2.5\"/>"
        // barre
        . "<rect x=\"$cx\" y=\"$cy\" width=\"$cw\" height=\"30\" fill=\"$INK\"/>"
        . '<text x="' . ($cx + 12) . '" y="' . ($cy + 20) . "\" class=\"m cream\" font-size=\"10\" font-weight=\"700\" letter-spacing=\"1.5\">LVL $num</text>"
        . '<text x="' . ($cx + $cw - 12) . '" y="' . ($cy + 20) . '" text-anchor="end" class="m cream" font-size="10" letter-spacing="1.2">' . x($tag) . '</text>'
        // visuel
        . '<rect x="' . ($cx + 1) . '" y="' . ($cy + 31) . '" width="' . ($cw - 2) . "\" height=\"168\" fill=\"url(#gr$id)\"/>"
        . ($img ? "<image x=\"" . ($cx + 1) . '" y="' . ($cy + 31) . '" width="' . ($cw - 2) . "\" height=\"168\" preserveAspectRatio=\"xMidYMid slice\" clip-path=\"url(#im$id)\" href=\"$img\" xlink:href=\"$img\"/>" : '')
        . '<path d="M' . $cx . ' ' . ($cy + 199) . 'h' . $cw . "\" stroke=\"$INK\" stroke-width=\"2.5\"/>"
        . '<rect x="' . ($cx + 10) . '" y="' . ($cy + 170) . '" width="' . (monoW($p['label'], 9.5, .1) + 16) . "\" height=\"20\" fill=\"$INK\"/>"
        . '<text x="' . ($cx + 18) . '" y="' . ($cy + 184) . '" class="m cream" font-size="9.5" font-weight="700" letter-spacing=".95">' . x($p['label']) . '</text>'
        // texte
        . '<text x="' . ($cx + 16) . '" y="' . ($cy + 232) . '" class="d ink" font-size="' . $fs . '">' . x($title) . '</text>'
        . $desc . $pills
        . '<path d="M' . $cx . ' ' . ($cy + 316) . 'h' . $cw . "\" stroke=\"$INK\" stroke-opacity=\".25\" stroke-width=\"1.5\" stroke-dasharray=\"4 4\"/>"
        . '<text x="' . ($cx + 16) . '" y="' . ($cy + 336) . '" class="m ink" font-size="10" letter-spacing="1.4" opacity=".6">' . x($p['footEn'] ?? '') . '</text>'
        . '<g class="enter"><text x="' . ($cx + $cw - 16) . '" y="' . ($cy + 336) . '" text-anchor="end" class="m ink" font-size="10.5" font-weight="700" letter-spacing="1.4">ENTER →</text></g>'
        . '</g>';
    $name = 'lvl-' . $p['slug'] . '.svg';
    svg($name, $W, $H, $p['title'] . ' - ' . $p['descEn'], $body);
    $cards[] = ['file' => $name, 'title' => $p['title'], 'slug' => $p['slug'], 'desc' => $p['descEn']];
}

// ══════════════════════════════════════════════════════════════
// 6. INVENTAIRE — stats + outils
// ══════════════════════════════════════════════════════════════
(function () use ($INK, $CREAM) {
    $W = 1200; $H = 560;
    $css = '.t{animation:rise .8s cubic-bezier(.2,.8,.2,1) .1s both}.ghost{animation:bob 7s ease-in-out infinite}';
    $slots = [
        ['[SLOT_A] GAME ENGINES', ['Unity', 'Unreal Engine', 'WebGL', 'Shader Graph', 'Blueprint', 'UI/UX']],
        ['[SLOT_B] LANGUAGES', ['C#', 'C++', 'JavaScript', 'SQL', 'PHP', 'MySQL', 'Python']],
        ['[SLOT_C] WEB & TOOLS', ['React', 'Next.js', 'Node.js', 'Supabase', 'Firebase', 'Tailwind', 'Figma', 'Blender', 'Aseprite', 'Rider', 'Git']],
    ];
    $s = ''; $y = 300;
    foreach ($slots as $k => [$label, $tags]) {
        $css .= ".s$k{animation:slideIn .6s ease " . (0.3 + $k * 0.15) . "s both}";
        $s .= "<g class=\"s$k\"><text x=\"48\" y=\"$y\" class=\"m cream\" font-size=\"11\" letter-spacing=\"2.2\" opacity=\".75\">" . x($label) . '</text>';
        $x = 48; $ty = $y + 14;
        foreach ($tags as $j => $t) {
            $w = monoW($t, 13) + 26;
            if ($x + $w > $W - 48) { $x = 48; $ty += 44; }
            $s .= "<rect x=\"$x\" y=\"$ty\" width=\"$w\" height=\"34\" fill=\"none\" stroke=\"$CREAM\" stroke-width=\"2\"/>"
                . '<text x="' . ($x + 13) . '" y="' . ($ty + 22) . '" class="m cream" font-size="13">' . x($t) . '</text>';
            $x += $w + 10;
        }
        $s .= '</g>';
        $y = $ty + 34 + 42;
    }
    $H = $y - 10;
    $stats = [['2+', 'ANNOUNCED PROJECTS'], ['6', 'YEARS OF DEV'], ['100+', 'DOWNLOADS'], ['9', 'SHIPPED GAMES']];
    $st = '';
    foreach ($stats as $k => [$n, $l]) {
        $x = 48 + $k * 282;
        $css .= ".n$k{animation:pop .5s cubic-bezier(.2,.8,.2,1.4) " . (0.4 + $k * 0.12) . "s both}";
        $st .= "<g class=\"n$k\"><rect x=\"$x\" y=\"166\" width=\"266\" height=\"92\" fill=\"none\" stroke=\"$CREAM\" stroke-opacity=\".35\" stroke-width=\"2\"/>"
             . '<text x="' . ($x + 18) . '" y="222" class="d cream" font-size="50">' . x($n) . '</text>'
             . '<text x="' . ($x + 18) . '" y="245" class="m cream" font-size="10.5" letter-spacing="1.8" opacity=".75">' . x($l) . '</text></g>';
    }
    $css .= '@keyframes pop{from{opacity:0;transform:scale(.9)}to{opacity:1;transform:none}}';
    $body = baseStyle(['Fredoka', 'JetBrains Mono'], $css)
        . "<rect width=\"$W\" height=\"$H\" fill=\"$INK\"/>" . grid('gi', $CREAM, .05, $W, $H)
        . "<g class=\"ghost\"><text x=\"1190\" y=\"" . ($H - 10) . "\" text-anchor=\"end\" class=\"d\" font-size=\"230\" fill=\"none\" stroke=\"$CREAM\" stroke-opacity=\".07\" stroke-width=\"3\">ITEMS</text></g>"
        . "<rect x=\"48\" y=\"30\" width=\"90\" height=\"28\" fill=\"$CREAM\"/><text x=\"61\" y=\"49\" class=\"m ink\" font-size=\"12\" font-weight=\"700\" letter-spacing=\"2.4\">ZONE 04</text>"
        . "<text x=\"156\" y=\"49\" class=\"m cream\" font-size=\"12\" letter-spacing=\"2.4\">INVENTORY</text>"
        . "<g class=\"t\"><text x=\"44\" y=\"140\" class=\"d cream\" font-size=\"88\" letter-spacing=\"-1\">THE <tspan fill=\"none\" stroke=\"$CREAM\" stroke-width=\"2.5\">TOOLBOX</tspan></text></g>"
        . $st . $s
        . "<rect x=\"1.5\" y=\"1.5\" width=\"" . ($W - 3) . "\" height=\"" . ($H - 3) . "\" fill=\"none\" stroke=\"$INK\" stroke-width=\"3\"/>";
    svg('inventory.svg', $W, $H, 'Inventory: Unity, Unreal Engine, C#, C++, JavaScript, React, PHP, MySQL', $body);
})();

// ══════════════════════════════════════════════════════════════
// 7. CONTINUE ? — lettres en vague + compte à rebours
// ══════════════════════════════════════════════════════════════
(function () use ($INK, $CREAM, $ACC) {
    $W = 1200; $H = 330;
    // Chasses approximatives de Fredoka Bold (em) pour centrer les lettres
    $adv = ['C' => .64, 'O' => .70, 'N' => .70, 'T' => .56, 'I' => .29, 'U' => .67, 'E' => .57, ' ' => .25, '?' => .52];
    $word = 'CONTINUE ?'; $fs = 132;
    $total = 0; foreach (str_split($word) as $c) $total += $adv[$c] * $fs;
    $x = ($W - $total) / 2; $letters = ''; $css = '';
    foreach (str_split($word) as $k => $c) {
        if ($c !== ' ') {
            $css .= ".w$k{animation:wave 1.8s ease-in-out " . ($k * 0.09) . 's infinite}';
            $letters .= "<g class=\"w$k\"><text x=\"" . round($x, 1) . "\" y=\"190\" class=\"d ink\" font-size=\"$fs\">$c</text></g>";
        }
        $x += $adv[$c] * $fs;
    }
    $css .= '@keyframes wave{0%,100%{transform:none}50%{transform:translateY(-22px)}}';
    // compte à rebours 9 → 0 comme sur une borne d'arcade
    $css .= '.cd{animation:cd 10s steps(1) infinite}';
    $cd = '';
    for ($n = 9; $n >= 0; $n--) {
        $k = 9 - $n;
        $css .= ".c$n{opacity:0;animation:c$n 10s steps(1) infinite}@keyframes c$n{0%{opacity:0}" . ($k * 10) . '%{opacity:1}' . (($k + 1) * 10) . '%{opacity:0}}';
        $cd .= "<text class=\"c$n m acc\" x=\"1098\" y=\"92\" text-anchor=\"middle\" font-size=\"44\" font-weight=\"700\">$n</text>";
    }
    $body = baseStyle(['Fredoka', 'JetBrains Mono'], $css)
        . "<rect width=\"$W\" height=\"$H\" fill=\"$CREAM\"/>" . grid('gc', $INK, .07, $W, $H)
        . "<rect x=\"48\" y=\"30\" width=\"90\" height=\"28\" fill=\"$INK\"/><text x=\"61\" y=\"49\" class=\"m cream\" font-size=\"12\" font-weight=\"700\" letter-spacing=\"2.4\">ZONE 05</text>"
        . "<rect x=\"160\" y=\"38\" width=\"10\" height=\"10\" class=\"ink pulse\"/><text x=\"180\" y=\"49\" class=\"m ink\" font-size=\"12\" letter-spacing=\"2.4\">AVAILABLE FOR FREELANCE WORK</text>"
        . "<rect x=\"1052\" y=\"40\" width=\"92\" height=\"68\" fill=\"none\" stroke=\"$INK\" stroke-width=\"3\"/>$cd"
        . $letters
        . "<text x=\"600\" y=\"244\" text-anchor=\"middle\" class=\"m ink\" font-size=\"15\" opacity=\".8\">Game dev, web, jam collaboration - open to all creative conversations.</text>"
        . "<g class=\"blink\"><text x=\"600\" y=\"292\" text-anchor=\"middle\" class=\"m ink\" font-size=\"14\" font-weight=\"700\" letter-spacing=\"3\">▼ INSERT COIN ▼</text></g>"
        . "<rect x=\"1.5\" y=\"1.5\" width=\"" . ($W - 3) . "\" height=\"" . ($H - 3) . "\" fill=\"none\" stroke=\"$INK\" stroke-width=\"3\"/>";
    svg('continue.svg', $W, $H, 'Continue? - Available for freelance work', $body);
})();

// ══════════════════════════════════════════════════════════════
// 8. BOUTONS DE CONTACT (polices système : fichiers légers)
// ══════════════════════════════════════════════════════════════
$buttons = [
    ['btn-portfolio.svg', 'PORTFOLIO ↗', true], ['btn-mail.svg', 'E-MAIL', false], ['btn-linkedin.svg', 'LINKEDIN', false],
    ['btn-itch.svg', 'ITCH.IO', false], ['btn-onnen.svg', 'ONNEN.GAMES', false],
];
foreach ($buttons as [$file, $label, $solid]) {
    $w = (int)ceil(monoW($label, 14, .1) + 56); $h = 62;
    $bg = $solid ? $INK : $CREAM; $fg = $solid ? $CREAM : $INK;
    $body = "<style>.m{font-family:'JetBrains Mono',Consolas,'Courier New',monospace;font-weight:700}"
          . ($solid ? '.p{animation:p 2.2s ease-in-out infinite}@keyframes p{0%,100%{transform:none}50%{transform:translate(-2px,-2px)}}@media (prefers-reduced-motion:reduce){.p{animation:none}}' : '') . '</style>'
          . '<rect x="7" y="7" width="' . ($w - 9) . '" height="' . ($h - 9) . "\" fill=\"$INK\" opacity=\".3\"/>"
          . '<g class="p"><rect x="1" y="1" width="' . ($w - 9) . '" height="' . ($h - 9) . "\" fill=\"$bg\" stroke=\"$INK\" stroke-width=\"2\"/>"
          . '<text x="' . (($w - 8) / 2) . '" y="' . (($h - 8) / 2 + 6) . "\" text-anchor=\"middle\" class=\"m\" fill=\"$fg\" font-size=\"14\" letter-spacing=\"1.4\">" . x($label) . '</text></g>';
    svg($file, $w, $h, $label, $body);
}

// ══════════════════════════════════════════════════════════════
// 9. PIED DE PAGE
// ══════════════════════════════════════════════════════════════
(function () use ($INK, $CREAM) {
    $W = 1200; $H = 54;
    $body = baseStyle(['JetBrains Mono'], '.coin{transform-origin:762px 31px;animation:flip 1.4s steps(2) infinite}@keyframes flip{50%{transform:scaleX(-1)}}')
        . "<rect width=\"$W\" height=\"$H\" fill=\"$INK\"/>"
        . '<text x="32" y="32" class="m cream" font-size="12" letter-spacing="1.6" opacity=".65">© ' . date('Y') . ' MATHIAS REBECCA</text>'
        . '<text x="600" y="32" text-anchor="middle" class="m cream" font-size="12" letter-spacing="1.6">GAME OVER - INSERT COIN</text><g class="coin"><text x="762" y="33" class="m cream" font-size="14">◐</text></g>'
        . "<text x=\"1168\" y=\"32\" text-anchor=\"end\" class=\"m cream\" font-size=\"12\" letter-spacing=\"1.6\" opacity=\".65\">FRANCE · PARIS · REMOTE ∞</text>";
    svg('footer.svg', $W, $H, 'Game over - insert coin', $body);
})();

// ══════════════════════════════════════════════════════════════
// 10. README.md
// ══════════════════════════════════════════════════════════════
$rows = '';
foreach (array_chunk($cards, 3) as $row) {
    $rows .= "<p align=\"center\">\n";
    foreach ($row as $c) {
        $rows .= "  <a href=\"$SITE/en/projects/{$c['slug']}/\"><img src=\"assets/{$c['file']}\" width=\"32%\" alt=\"" . x($c['title'] . ' - ' . $c['desc']) . "\"></a>\n";
    }
    $rows .= "</p>\n";
}
$ved = $data['VEDETTE']['slug'] ?? 'etherias-end';
$readme = <<<MD
<!-- Généré par tools/generate.php : modifier le script plutôt que ce fichier. -->
<a href="$SITE/en/"><img src="assets/header.svg" width="100%" alt="Mathias REBECCA - Game &amp; Web Developer based in France. Unity, Unreal Engine, C#, C++, Web."></a>

<img src="assets/marquee-skills.svg" width="100%" alt="Unity, Unreal Engine, C#, C++, Game Design, Level Design, React, WebGL, Game Jams">

<a href="$SITE/en/projects/$ved/"><img src="assets/boss.svg" width="100%" alt="Etheria's End - featured project: a 3rd-person adventure on floating islands made with Unreal Engine. Lead dev &amp; game designer. Steam, June 2026."></a>

<img src="assets/marquee-levels.svg" width="100%" alt="Projects">

<a href="$SITE/en/#projects"><img src="assets/title-levels.svg" width="100%" alt="My projects - level select"></a>

$rows
<p align="center"><sub><code>// CLICK A LEVEL TO ENTER - every card opens its page on <a href="$SITE/en/">mrebecca.net</a></code></sub></p>

<img src="assets/inventory.svg" width="100%" alt="Inventory - engines: Unity, Unreal Engine, WebGL, Shader Graph, Blueprint. Languages: C#, C++, JavaScript, SQL, PHP, Python. Web &amp; tools: React, Next.js, Node.js, Supabase, Figma, Blender, Git.">

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/0nnen/0nnen/output/snake-dark.svg">
  <img src="https://raw.githubusercontent.com/0nnen/0nnen/output/snake.svg" width="100%" alt="Contribution graph eaten by an arcade snake">
</picture>

<a href="mailto:mathias.rebecca2@gmail.com"><img src="assets/continue.svg" width="100%" alt="Continue? Available for freelance work"></a>

<p align="center">
  <a href="$SITE/en/"><img src="assets/btn-portfolio.svg" height="48" alt="Portfolio"></a>
  <a href="mailto:mathias.rebecca2@gmail.com"><img src="assets/btn-mail.svg" height="48" alt="E-mail"></a>
  <a href="https://www.linkedin.com/in/mathias-rebecca"><img src="assets/btn-linkedin.svg" height="48" alt="LinkedIn"></a>
  <a href="https://onnen.itch.io"><img src="assets/btn-itch.svg" height="48" alt="itch.io"></a>
  <a href="https://onnen.games"><img src="assets/btn-onnen.svg" height="48" alt="onnen.games"></a>
</p>

<img src="assets/footer.svg" width="100%" alt="Game over - insert coin">

MD;
file_put_contents("$root/README.md", $readme);
echo "README.md écrit\n";
