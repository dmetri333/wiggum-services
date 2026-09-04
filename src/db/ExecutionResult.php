<?php
namespace wiggum\services\db;

final class ExecutionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $affectedRows,
        public readonly ?string $lastInsertId
    ) {
    }
}
