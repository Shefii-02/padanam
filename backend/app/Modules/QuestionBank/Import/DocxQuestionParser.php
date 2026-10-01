<?php

namespace App\Modules\QuestionBank\Import;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\IOFactory;

/**
 * Word (.docx) format – the way teachers already type papers:
 *
 *   1. Who founded the SNDP Yogam?
 *   ML: SNDP യോഗം സ്ഥാപിച്ചത് ആര്?          (optional second language line)
 *   A) Sree Narayana Guru || ശ്രീനാരായണഗുരു    (" || " separates the second language)
 *   B) Ayyankali
 *   C) Chattampi Swamikal
 *   D) Mannathu Padmanabhan
 *   Answer: A
 *   Solution: Founded in 1903 …               (lines until the next question are added)
 *   Subject: GK | Topic: Renaissance | Difficulty: easy | Labels: LDC, PYQ
 *
 * Images anywhere (question, option, solution) are saved and kept in place.
 */
class DocxQuestionParser
{
    private const Q = '/^(?:Q\s*\.?\s*)?(\d{1,4})\s*[\.\):]\s*(.*)$/u';
    private const OPT = '/^\(?([A-Fa-f])\s*[\.\)]\s*(.*)$/u';
    private const ANS = '/^(?:Ans(?:wer)?|Correct(?:\s+answer)?|Key)\s*[:\-–]\s*(.+)$/iu';
    private const SOL = '/^(?:Solution|Explanation|Sol|Exp)\s*[:\-–]\s*(.*)$/iu';
    private const SECOND = '/^([A-Za-z]{2})\s*:\s*(.+)$/u';   // "ML: ..."

    public function __construct(private string $imageDir = 'questions') {}

    /** @return ParsedRow[] */
    public function parse(string $path, string $lang, string $secondLang = 'ml'): array
    {
        $lines = [];
        foreach (IOFactory::load($path)->getSections() as $section) {
            $this->collect($section, $lines);
        }

        $rows = [];
        $cur = null;
        $mode = null;   // question|option|solution
        $optIndex = -1;
        foreach ($lines as $n => $html) {
            $plain = trim(html_entity_decode(strip_tags($html)));
            if ($plain === '' && ! str_contains($html, '<img')) {
                continue;
            }
            if (preg_match(self::Q, $plain) && ($cur === null || $mode !== 'question' || $cur->options)) {
                if ($cur) {
                    $rows[] = $cur->finalize();
                }
                $cur = new ParsedRow($n + 1);
                $cur->translations[$lang]['text'] = $this->stripPrefix($html, self::Q);
                $mode = 'question';
                $optIndex = -1;

                continue;
            }
            if (! $cur) {
                continue;   // text before the first question (title, instructions)
            }
            if (preg_match(self::ANS, $plain, $m)) {
                is_numeric(trim($m[1])) && ! $cur->options ? $cur->numeric = trim($m[1]) : $cur->answers = ParsedRow::letters($m[1]);
                $mode = 'answer';

                continue;
            }
            if (preg_match(self::SOL, $plain)) {
                $cur->translations[$lang]['solution'] = $this->stripPrefix($html, self::SOL);
                $mode = 'solution';

                continue;
            }
            if ($this->meta($cur, $plain)) {
                continue;
            }
            if (preg_match(self::OPT, $plain, $m) && in_array($mode, ['question', 'option'], true)) {
                $optIndex = ord(strtoupper($m[1])) - 65;
                $body = $this->stripPrefix($html, self::OPT);
                [$a, $b] = array_pad(preg_split('/\s*\|\|\s*/u', $body, 2), 2, null);
                $cur->options[$optIndex] = array_filter([$lang => trim($a), $secondLang => $b !== null ? trim($b) : null]);
                $mode = 'option';

                continue;
            }
            if ($mode === 'question' && preg_match(self::SECOND, $plain, $m) && strtolower($m[1]) !== $lang && ! preg_match(self::OPT, $plain)) {
                $cur->translations[strtolower($m[1])]['text'] = $this->stripPrefix($html, self::SECOND);

                continue;
            }
            // continuation lines
            match ($mode) {
                'question' => $cur->translations[$lang]['text'] .= '<br>'.$html,
                'solution' => $cur->translations[$lang]['solution'] .= '<br>'.$html,
                'option' => $cur->options[$optIndex][$lang] = ($cur->options[$optIndex][$lang] ?? '').' '.$html,
                default => null,
            };
        }
        if ($cur) {
            $rows[] = $cur->finalize();
        }
        foreach ($rows as $r) {
            ksort($r->options);
            $r->options = array_values($r->options);
        }

        return $rows;
    }

    private function meta(ParsedRow $r, string $plain): bool
    {
        if (! preg_match('/^(Subject|Topic|Difficulty|Level|Marks|Negative|Labels|Tags|Year|Source)\s*:/i', $plain)) {
            return false;
        }
        foreach (preg_split('/\s*\|\s*/', $plain) as $part) {
            if (! preg_match('/^(\w+)\s*:\s*(.*)$/', $part, $m)) {
                continue;
            }
            $v = trim($m[2]);
            match (strtolower($m[1])) {
                'subject' => $r->subject = $v,
                'topic' => $r->topic = $v,
                'difficulty', 'level' => $r->difficulty = strtolower($v),
                'marks' => $r->marks = (float) $v,
                'negative' => $r->negative = (float) $v,
                'labels', 'tags' => $r->labels = array_values(array_filter(array_map('trim', explode(',', $v)))),
                'year' => $r->year = (int) $v,
                'source' => $r->source = $v,
                default => null,
            };
        }

        return true;
    }

    /** Flattens the document into paragraph-level HTML lines. */
    private function collect($container, array &$lines): void
    {
        foreach ($container->getElements() as $el) {
            if ($el instanceof Table) {
                foreach ($el->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $this->collect($cell, $lines);
                    }
                }
            } elseif ($el instanceof TextBreak) {
                $lines[] = '';
            } elseif ($el instanceof AbstractContainer && ! ($el instanceof \PhpOffice\PhpWord\Element\Section)) {
                $lines[] = $this->inline($el);
            } elseif ($el instanceof Text) {
                $lines[] = $this->text($el);
            } elseif ($el instanceof Image) {
                $lines[] = $this->image($el);
            }
        }
    }

    private function inline(AbstractContainer $run): string
    {
        $html = '';
        foreach ($run->getElements() as $el) {
            if ($el instanceof Text) {
                $html .= $this->text($el);
            } elseif ($el instanceof Image) {
                $html .= $this->image($el);
            } elseif ($el instanceof TextBreak) {
                $html .= '<br>';
            } elseif ($el instanceof AbstractContainer) {
                $html .= $this->inline($el);
            }
        }

        return $html;
    }

    private function text(Text $t): string
    {
        $s = e($t->getText());
        $f = $t->getFontStyle();
        if (is_object($f)) {
            if ($f->isBold()) {
                $s = "<b>$s</b>";
            }
            if ($f->isSuperScript()) {
                $s = "<sup>$s</sup>";
            }
            if ($f->isSubScript()) {
                $s = "<sub>$s</sub>";
            }
        }

        return $s;
    }

    private function image(Image $img): string
    {
        try {
            $data = $img->getImageStringData(true);
            if (! $data) {
                return '';
            }
            $ext = strtolower($img->getImageExtension() ?: 'png');
            $path = $this->imageDir.'/'.now()->format('Y/m').'/'.Str::uuid().'.'.$ext;
            Storage::disk('public')->put($path, base64_decode($data));

            return '<img src="'.Storage::disk('public')->url($path).'" alt="">';
        } catch (\Throwable) {
            return '';
        }
    }

    /** Removes "1." / "A)" / "Answer:" prefix but keeps inline HTML (bold, images) around the rest. */
    private function stripPrefix(string $html, string $pattern): string
    {
        $plain = ltrim(html_entity_decode(strip_tags($html)));
        if (! preg_match($pattern, trim($plain), $m)) {
            return $html;
        }
        $skip = mb_strlen(trim($plain)) - mb_strlen(end($m));   // characters of the "1." / "A)" marker
        $out = '';
        $i = 0;
        $html = ltrim($html);
        $len = mb_strlen($html);
        while ($i < $len) {
            $ch = mb_substr($html, $i, 1);
            if ($ch === '<') {                       // keep tags
                $close = mb_strpos($html, '>', $i);
                $out .= mb_substr($html, $i, $close - $i + 1);
                $i = $close + 1;

                continue;
            }
            if ($skip <= 0) {
                $out .= mb_substr($html, $i);
                break;
            }
            if ($ch === '&') {                       // an entity counts as one character
                $semi = mb_strpos($html, ';', $i);
                $i = $semi !== false && $semi - $i < 10 ? $semi + 1 : $i + 1;
            } else {
                $i++;
            }
            $skip--;
        }

        return trim(preg_replace('#<(\w+)[^>]*>\s*</\1>#u', '', $out));
    }
}
