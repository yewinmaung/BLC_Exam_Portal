<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class QuestionImportService
{
    public function __construct(private EncryptionService $encryption)
    {
    }

    /**
     * Moodle-style import format (save as .txt or paste):
     *
     * [MCQ] What is 2+2? (2 marks)
     * A. 3
     * B. 4 *
     * C. 5
     *
     * [TRUE_FALSE] The sky is blue? (1 mark)
     * True *
     * False
     */
    public function parseText(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $blocks = preg_split('/\n(?=\[(?:MCQ|TRUE_FALSE|ESSAY|DOCUMENT|FILE_UPLOAD)\])/i', $text) ?: [];
        $questions = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '' || !preg_match('/^\[(\w+)\]\s*(.+)$/m', $block, $firstLine)) {
                continue;
            }

            $typeKey = strtoupper(str_replace(' ', '_', $firstLine[1]));
            $typeMap = [
                'MCQ' => 'mcq',
                'TRUE_FALSE' => 'true_false',
                'ESSAY' => 'essay',
                'DOCUMENT' => 'document',
                'FILE_UPLOAD' => 'file_upload',
            ];

            if (!isset($typeMap[$typeKey])) {
                continue;
            }

            $type = $typeMap[$typeKey];
            $rest = trim(substr($block, strlen($firstLine[0])));
            $lines = explode("\n", $rest);
            $headerLine = array_shift($lines);
            $marks = 1;
            $content = $headerLine;

            if (preg_match('/^(.+?)\s*\((\d+)\s*marks?\)\s*$/i', $headerLine, $m)) {
                $content = trim($m[1]);
                $marks = max(1, (int) $m[2]);
            }

            $answers = [];
            if (in_array($type, ['mcq', 'true_false'], true)) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $isCorrect = str_contains($line, '*');
                    $line = str_replace('*', '', $line);
                    if (preg_match('/^([A-Z])[.\)]\s*(.+)$/i', $line, $m)) {
                        $answers[] = ['content' => trim($m[2]), 'is_correct' => $isCorrect];
                    } elseif (preg_match('/^(True|False)\s*$/i', $line, $m)) {
                        $answers[] = ['content' => ucfirst(strtolower($m[1])), 'is_correct' => $isCorrect];
                    }
                }
            }

            $questions[] = [
                'type' => $type,
                'content' => $content,
                'marks' => $marks,
                'answers' => $answers,
            ];
        }

        return $questions;
    }

    public function extractTextFromFile(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return match ($ext) {
            'txt' => file_get_contents($file->getRealPath()) ?: '',
            'docx' => $this->extractFromDocx($file->getRealPath()),
            'pdf' => $this->extractFromPdf($file->getRealPath()),
            'doc' => $this->extractFromDoc($file->getRealPath()),
            default => '',
        };
    }

    public function importFromFile(Exam $exam, UploadedFile $file, ?int $categoryId = null): int
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $text = $this->extractTextFromFile($file);
        $parsed = $this->parseText($text);

        if (count($parsed) > 0) {
            return $this->importParsed($exam, $parsed, $categoryId);
        }

        if (in_array($ext, ['pdf', 'doc', 'docx'], true)) {
            $this->createDocumentQuestionFromFile($exam, $file, $categoryId);

            return 1;
        }

        return 0;
    }

    public function importParsed(Exam $exam, array $parsed, ?int $categoryId = null): int
    {
        $order = $exam->questions()->count();
        $count = 0;

        foreach ($parsed as $item) {
            $order++;
            $question = Question::create([
                'exam_id' => $exam->id,
                'type' => $item['type'],
                'content_encrypted' => $this->encryption->encrypt($item['content']),
                'marks' => $item['marks'],
                'difficulty' => 'medium',
                'category_id' => $categoryId,
                'order' => $order,
            ]);

            foreach ($item['answers'] ?? [] as $i => $answerData) {
                Answer::create([
                    'question_id' => $question->id,
                    'content_encrypted' => $this->encryption->encrypt($answerData['content']),
                    'is_correct' => !empty($answerData['is_correct']),
                    'order' => $i + 1,
                ]);
            }

            $count++;
        }

        return $count;
    }

    public function storeAttachment(UploadedFile $file, int $examId): array
    {
        $path = $file->store("exams/{$examId}/questions", 'public');

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
        ];
    }

    private function createDocumentQuestionFromFile(Exam $exam, UploadedFile $file, ?int $categoryId): void
    {
        $attachment = $this->storeAttachment($file, $exam->id);
        $order = $exam->questions()->count() + 1;

        Question::create([
            'exam_id' => $exam->id,
            'type' => 'document',
            'content_encrypted' => $this->encryption->encrypt('Read the attached document: ' . $attachment['name']),
            'marks' => 1,
            'difficulty' => 'medium',
            'category_id' => $categoryId,
            'order' => $order,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
            'attachment_mime' => $attachment['mime'],
        ]);
    }

    private function extractFromDocx(string $path): string
    {
        if (!class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xml) {
            return '';
        }

        $xml = str_replace(['</w:p>', '</w:tab>', '<w:tab/>'], ["\n", "\t", "\t"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES, 'UTF-8');
    }

    private function extractFromPdf(string $path): string
    {
        $content = @file_get_contents($path);
        if (!$content) {
            return '';
        }

        $text = '';
        if (preg_match_all('/\((?:\\\\.|[^\\\\])*?\)/s', $content, $matches)) {
            foreach ($matches[0] as $match) {
                $part = substr($match, 1, -1);
                $part = preg_replace('/\\\\([nrtbf()\\\\])/', '', $part);
                if (preg_match('/[\x20-\x7E\xA0-\xFF]/', $part)) {
                    $text .= $part . "\n";
                }
            }
        }

        return $text;
    }

    private function extractFromDoc(string $path): string
    {
        $content = @file_get_contents($path);
        if (!$content) {
            return '';
        }

        if (preg_match_all('/[\x20-\x7E]{6,}/', $content, $matches)) {
            return implode("\n", $matches[0]);
        }

        return '';
    }
}
