@php
    $questions = $result->exam?->questions ?? collect();
    $ansMap = ($result->attempt?->studentAnswers ?? collect())->keyBy('question_id');
@endphp

@if($questions->isEmpty())
    <div class="text-muted small py-2">No questions available for this exam.</div>
@else
    @foreach($questions as $i => $q)
    @php
        $studentAnswer = $ansMap->get($q->id);
        $isCorrect     = $studentAnswer?->is_correct ?? false;
    @endphp
    <div class="review-card {{ $isCorrect ? 'review-correct' : 'review-wrong' }}">
        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
            <span class="q-number">Q{{ $i + 1 }}</span>
            <span class="badge" style="background:#f0f4ff;color:var(--blc-navy-2,#0f3a7a);font-size:0.7rem">
                {{ strtoupper(str_replace('_', ' ', $q->type)) }}
            </span>
            <span class="badge" style="background:#f0fdf4;color:#166534;font-size:0.7rem">
                {{ $q->marks }} mark{{ $q->marks !== 1 ? 's' : '' }}
            </span>
            @if($studentAnswer)
            <span class="ms-auto badge {{ $isCorrect ? 'bg-success' : 'bg-danger' }}">
                {{ $isCorrect ? '✓ Correct' : '✗ Wrong' }}
            </span>
            @else
            <span class="ms-auto badge bg-secondary">— Not answered</span>
            @endif
        </div>

        <div class="q-text mb-3" style="font-weight:600;color:var(--blc-navy,#0b2a5b)">
            {{ $q->decrypted_content }}
        </div>

        @if($studentAnswer)
        <div class="mb-2">
            <div class="text-muted small mb-1" style="font-weight:600">Your Answer:</div>
            @if($q->type === 'fill_blank')
                <span class="student-answer-pill {{ $isCorrect ? 'correct' : 'wrong' }}">
                    {{ $studentAnswer->answer_text ?? '(no answer)' }}
                </span>
            @elseif(in_array($q->type, ['mcq', 'true_false']) && $studentAnswer->answer)
                <span class="student-answer-pill {{ $isCorrect ? 'correct' : 'wrong' }}">
                    {{ $studentAnswer->answer->decrypted_content }}
                </span>
            @elseif($q->type === 'essay')
                <div class="p-2 rounded" style="background:#f8faff;border:1px solid #e8edf5;font-size:0.875rem">
                    {{ $studentAnswer->answer_text ?? '(no answer)' }}
                </div>
            @else
                <span class="text-muted small">(no answer submitted)</span>
            @endif
        </div>
        @else
        <div class="mb-2">
            <span class="text-muted small">No answer recorded for this question.</span>
        </div>
        @endif

        @if($q->type === 'fill_blank')
        <div>
            <div class="text-muted small mb-1" style="font-weight:600">Accepted Answers:</div>
            <div class="d-flex flex-wrap gap-1">
                @foreach($q->answers->where('is_blank_answer', true) as $a)
                <span class="student-answer-pill correct">{{ $a->decrypted_content }}</span>
                @endforeach
            </div>
        </div>
        @elseif(in_array($q->type, ['mcq', 'true_false']))
        <div>
            <div class="text-muted small mb-1" style="font-weight:600">Correct Answer:</div>
            @foreach($q->answers->where('is_correct', true) as $a)
            <span class="student-answer-pill correct">{{ $a->decrypted_content }}</span>
            @endforeach
        </div>
        @endif
    </div>
    @endforeach
@endif
