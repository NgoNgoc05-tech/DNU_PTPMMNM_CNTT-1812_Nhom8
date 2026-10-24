<?php

declare(strict_types=1);

// Bộ kiểm thử tối giản theo kiểu Lab 05: in PASS/FAIL từng ca và tổng TESTS=.. FAILED=..
$tests = 0;
$failed = 0;

function same(mixed $actual, mixed $expected, string $name): void
{
    global $tests, $failed;
    $tests++;
    if ($actual === $expected) {
        echo "PASS $name\n";
    } else {
        $failed++;
        echo "FAIL $name expected=" . var_export($expected, true) . ' actual=' . var_export($actual, true) . "\n";
    }
}

function near(float $actual, float $expected, string $name, float $tolerance = 0.01): void
{
    global $tests, $failed;
    $tests++;
    if (abs($actual - $expected) <= $tolerance) {
        echo "PASS $name\n";
    } else {
        $failed++;
        echo "FAIL $name expected=$expected actual=$actual\n";
    }
}

/** Ca kiểm thử mong đợi một AppError với mã cụ thể. */
function throwsCode(callable $fn, string $code, string $name): void
{
    try {
        $fn();
        same('NO_ERROR', $code, $name);
    } catch (AppError $e) {
        same($e->errorCode, $code, $name);
    }
}

function finish(): never
{
    global $tests, $failed;
    echo "TESTS=$tests FAILED=$failed\n";
    exit($failed === 0 ? 0 : 1);
}
