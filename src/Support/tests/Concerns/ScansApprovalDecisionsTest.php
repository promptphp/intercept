<?php

declare(strict_types=1);

use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use PromptPHP\Intercept\Support\Tests\Fixtures\ApprovalDecisionScanner;
use PromptPHP\Intercept\Support\ValueObjects\ApprovalDecisionSegment;

function scanApprovalDecisions(?Decisions $decisions): array
{
    return (new ApprovalDecisionScanner)->segments($decisions);
}

it('returns no segments when the prompt carries no approval decisions', function (): void {
    expect(scanApprovalDecisions(null))->toBe([]);
});

it('extracts edited tool arguments', function (): void {
    $segments = scanApprovalDecisions(Decisions::from([
        'call_1' => Decision::edit(['recipient' => 'victor@example.com']),
    ]));

    expect($segments)->toHaveCount(1);
    expect($segments[0])->toBeInstanceOf(ApprovalDecisionSegment::class);
    expect($segments[0]->toolCallId)->toBe('call_1');
    expect($segments[0]->field)->toBe('arguments.recipient');
    expect($segments[0]->text)->toBe('victor@example.com');
});

it('extracts nested tool arguments using dot paths', function (): void {
    $segments = scanApprovalDecisions(Decisions::from([
        'call_1' => Decision::edit([
            'filters' => [
                'contact' => ['email' => 'victor@example.com'],
            ],
        ]),
    ]));

    expect($segments)->toHaveCount(1);
    expect($segments[0]->field)->toBe('arguments.filters.contact.email');
});

it('extracts list arguments using their numeric index', function (): void {
    $segments = scanApprovalDecisions(Decisions::from([
        'call_1' => Decision::edit([
            'recipients' => ['first@example.com', 'second@example.com'],
        ]),
    ]));

    expect($segments)->toHaveCount(2);
    expect($segments[0]->field)->toBe('arguments.recipients.0');
    expect($segments[1]->field)->toBe('arguments.recipients.1');
});

it('extracts integer arguments so unquoted card numbers are still scanned', function (): void {
    $segments = scanApprovalDecisions(Decisions::from([
        'call_1' => Decision::edit(['card' => 4111111111111111]),
    ]));

    expect($segments)->toHaveCount(1);
    expect($segments[0]->text)->toBe('4111111111111111');
});

it('ignores argument values that cannot carry a detectable value', function (): void {
    $segments = scanApprovalDecisions(Decisions::from([
        'call_1' => Decision::edit([
            'enabled'   => true,
            'disabled'  => false,
            'missing'   => null,
            'threshold' => 1.5,
        ]),
    ]));

    expect($segments)->toBe([]);
});

it('ignores blank argument strings', function (): void {
    $segments = scanApprovalDecisions(Decisions::from([
        'call_1' => Decision::edit([
            'empty'      => '',
            'whitespace' => "  \n ",
        ]),
    ]));

    expect($segments)->toBe([]);
});

it('extracts rejection results', function (): void {
    $segments = scanApprovalDecisions(Decisions::from([
        'call_1' => Decision::reject('Cancelled. Contact victor@example.com instead.'),
    ]));

    expect($segments)->toHaveCount(1);
    expect($segments[0]->toolCallId)->toBe('call_1');
    expect($segments[0]->field)->toBe('result');
    expect($segments[0]->text)->toBe('Cancelled. Contact victor@example.com instead.');
});

it('ignores rejections that carry no result', function (): void {
    expect(scanApprovalDecisions(Decisions::from([
        'call_1' => Decision::reject(),
    ])))->toBe([]);
});

it('ignores approved decisions because they carry no operator input', function (): void {
    expect(scanApprovalDecisions(Decisions::from([
        'call_1' => Decision::approve(),
        'call_2' => true,
    ])))->toBe([]);
});

it('extracts segments from the wildcard decision', function (): void {
    $segments = scanApprovalDecisions(Decisions::from([
        '*' => Decision::reject('Rejected by victor@example.com.'),
    ]));

    expect($segments)->toHaveCount(1);
    expect($segments[0]->toolCallId)->toBe('*');
});

it('extracts segments across multiple decisions', function (): void {
    $segments = scanApprovalDecisions(Decisions::from([
        'call_1' => Decision::edit(['recipient' => 'victor@example.com']),
        'call_2' => Decision::approve(),
        'call_3' => Decision::reject('Use 192.168.1.1 instead.'),
    ]));

    expect($segments)->toHaveCount(2);
    expect($segments[0]->toolCallId)->toBe('call_1');
    expect($segments[1]->toolCallId)->toBe('call_3');
});

it('returns no segments for an edit that carries no arguments', function (): void {
    expect(scanApprovalDecisions(Decisions::from([
        'call_1' => Decision::edit([]),
    ])))->toBe([]);
});
