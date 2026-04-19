<?php
// code128.php
// Implementação simples de Code128 (conjunto B) para FPDF

class PDF_Code128 extends FPDF
{
    // Tabela de padrões Code128 (0..106)
    protected $T128 = [
        "212222",
        "222122",
        "222221",
        "121223",
        "121322",
        "131222",
        "122213",
        "122312",
        "132212",
        "221213",
        "221312",
        "231212",
        "112232",
        "122132",
        "122231",
        "113222",
        "123122",
        "123221",
        "223211",
        "221132",
        "221231",
        "213212",
        "223112",
        "312131",
        "311222",
        "321122",
        "321221",
        "312212",
        "322112",
        "322211",
        "212123",
        "212321",
        "232121",
        "111323",
        "131123",
        "131321",
        "112313",
        "132113",
        "132311",
        "211313",
        "231113",
        "231311",
        "112133",
        "112331",
        "132131",
        "113123",
        "113321",
        "133121",
        "313121",
        "211331",
        "231131",
        "213113",
        "213311",
        "213131",
        "311123",
        "311321",
        "331121",
        "312113",
        "312311",
        "332111",
        "314111",
        "221411",
        "431111",
        "111224",
        "111422",
        "121124",
        "121421",
        "141122",
        "141221",
        "112214",
        "112412",
        "122114",
        "122411",
        "142112",
        "142211",
        "241211",
        "221114",
        "413111",
        "241112",
        "134111",
        "111242",
        "121142",
        "121241",
        "114212",
        "124112",
        "124211",
        "411212",
        "421112",
        "421211",
        "212141",
        "214121",
        "412121",
        "111143",
        "111341",
        "131141",
        "114113",
        "114311",
        "411113",
        "411311",
        "113141",
        "114131",
        "311141",
        "411131",
        "211412",
        "211214",
        "211232",
        "23311120"
    ];

    // Conjunto de caracteres para Code128-B (ASCII 32-126)
    protected $codesetB =
    " !\"#$%&'()*+,-./0123456789:;<=>?@" .
        "ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`" .
        "abcdefghijklmnopqrstuvwxyz{|}~";

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4')
    {
        parent::__construct($orientation, $unit, $format);
    }

    /**
     * Desenha um código de barras Code128-B
     *
     * @param float  $x   posição X
     * @param float  $y   posição Y
     * @param string $code texto a codificar
     * @param float  $w   largura "por caractere" (não total)
     * @param float  $h   altura do código
     */
    public function Code128($x, $y, $code, $totalWidth, $h)
    {
        if ($code === '') {
            return;
        }

        // START B (104)
        $values   = [104];
        $checksum = 104;
        $weight   = 1;

        $len = strlen($code);

        // monta valores para cada caractere (conjunto B)
        for ($i = 0; $i < $len; $i++) {
            $c   = $code[$i];
            $pos = strpos($this->codesetB, $c);

            if ($pos === false) {
                $pos = strpos($this->codesetB, ' '); // se não achar, vira espaço
            }

            $values[]   = $pos;
            $checksum  += $pos * $weight;
            $weight++;
        }

        // checksum
        $checksum = $checksum % 103;
        $values[] = $checksum;

        // STOP (106)
        $values[] = 106;

        // 1) primeiro conta quantos "módulos" de barra/ espaço teremos
        $totalModules = 0;
        foreach ($values as $val) {
            if (!isset($this->T128[$val])) {
                continue;
            }
            $pattern = $this->T128[$val]; // ex: "212222"
            $lenPat  = strlen($pattern);
            for ($i = 0; $i < $lenPat; $i++) {
                $totalModules += intval($pattern[$i]);
            }
        }

        if ($totalModules <= 0) {
            return;
        }

        // 2) define a largura de UM módulo para caber no totalWidth desejado
        $module = $totalWidth / $totalModules;

        // 3) desenha barras e espaços
        foreach ($values as $val) {
            if (!isset($this->T128[$val])) {
                continue;
            }

            $pattern = $this->T128[$val];
            $lenPat  = strlen($pattern);

            for ($i = 0; $i < $lenPat; $i++) {
                $lineModules = intval($pattern[$i]);
                $lineWidth   = $module * $lineModules;

                // índice par = barra (preto), ímpar = espaço
                if ($i % 2 === 0) {
                    $this->Rect($x, $y, $lineWidth, $h, 'F');
                }

                $x += $lineWidth;
            }
        }
    }
}
