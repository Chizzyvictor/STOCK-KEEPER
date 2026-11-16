<?php

declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';



// --------------------------------------------------
// 1. JSON RESPONSE
// --------------------------------------------------

function respond(bool $success, string $message, array $data = [], int $status = 200): void
{
    apiRespond($success ? 'success' : 'error', $message, $data, $status);
}

// --------------------------------------------------
// 2. SQLITE3 HELPERS
// --------------------------------------------------

$cid = (int) $_SESSION['cid'];

$action = apiInput('action');

// --------------------------------------------------
// 5. DATABASE CONNECTION
// --------------------------------------------------

$db = null;

try {
    $db = stockKeeperOpenDatabase();

    if (method_exists($db, 'enableExceptions')) {
        $db->enableExceptions(true);
    }


    // --------------------------------------------------
    // 6. UNIT ACTIONS
    // --------------------------------------------------

    switch ($action) {

        // ----------------------------------------------
        // LIST UNITS
        // ----------------------------------------------

        case 'list':

            $stmt = $db->prepare(
                'SELECT
                    unit_id,
                    unit_name,
                    unit_symbol,
                    description,
                    is_active,
                    created_at
                 FROM product_units
                 WHERE cid = :cid
                 ORDER BY unit_name COLLATE NOCASE ASC'
            );

            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException(
                    'Unable to prepare unit list query.'
                );
            }

            try {
                $result = apiExecuteStatement($stmt, [
                    ':cid' => $cid
                ]);

                $units = [];

                try {
                    while (
                        ($row = $result->fetchArray(SQLITE3_ASSOC))
                        !== false
                    ) {
                        $units[] = $row;
                    }
                } finally {
                    $result->finalize();
                }
            } finally {
                $stmt->close();
            }

            respond(
                true,
                'Units loaded successfully.',
                ['units' => $units]
            );

        // ----------------------------------------------
        // CREATE UNIT
        // ----------------------------------------------

        case 'create':

            $name = apiInput('unit_name');
            $symbol = apiInput('unit_symbol');
            $description = apiInput('description');

            if ($name === '') {
                respond(false, 'Enter a unit name.', [], 422);
            }

            if (apiTextLength($name) > 100) {
                respond(
                    false,
                    'Unit name must not exceed 100 characters.',
                    [],
                    422
                );
            }

            if (apiTextLength($symbol) > 30) {
                respond(
                    false,
                    'Unit symbol must not exceed 30 characters.',
                    [],
                    422
                );
            }

            if (apiTextLength($description) > 500) {
                respond(
                    false,
                    'Description must not exceed 500 characters.',
                    [],
                    422
                );
            }

            $duplicate = apiFetchOne(
                $db,
                'SELECT unit_id
                 FROM product_units
                 WHERE cid = :cid
                   AND unit_name = :name COLLATE NOCASE
                 LIMIT 1',
                [
                    ':cid' => $cid,
                    ':name' => $name
                ]
            );

            if ($duplicate !== null) {
                respond(
                    false,
                    'A unit with this name already exists.',
                    [],
                    409
                );
            }

            $stmt = $db->prepare(
                'INSERT INTO product_units
                    (
                        cid,
                        unit_name,
                        unit_symbol,
                        description,
                        is_active
                    )
                 VALUES
                    (
                        :cid,
                        :name,
                        :symbol,
                        :description,
                        1
                    )'
            );

            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException(
                    'Unable to prepare unit insert query.'
                );
            }

            try {
                $result = apiExecuteStatement($stmt, [
                    ':cid' => $cid,
                    ':name' => $name,
                    ':symbol' => $symbol,
                    ':description' => $description
                ]);

                $result->finalize();
            } finally {
                $stmt->close();
            }

            respond(
                true,
                'Unit added successfully.',
                [
                    'unit_id' => (int) $db->lastInsertRowID()
                ]
            );

        // ----------------------------------------------
        // UPDATE UNIT
        // ----------------------------------------------

        case 'update':

            $id = filter_var(
                $_POST['unit_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $name = apiInput('unit_name');
            $symbol = apiInput('unit_symbol');
            $description = apiInput('description');

            $status = filter_var(
                $_POST['status'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (
                $id === false ||
                $id === null ||
                $id < 1
            ) {
                respond(false, 'Invalid unit selected.', [], 422);
            }

            if ($name === '') {
                respond(false, 'Enter a unit name.', [], 422);
            }

            if (apiTextLength($name) > 100) {
                respond(
                    false,
                    'Unit name must not exceed 100 characters.',
                    [],
                    422
                );
            }

            if (apiTextLength($symbol) > 30) {
                respond(
                    false,
                    'Unit symbol must not exceed 30 characters.',
                    [],
                    422
                );
            }

            if (apiTextLength($description) > 500) {
                respond(
                    false,
                    'Description must not exceed 500 characters.',
                    [],
                    422
                );
            }

            if (
                $status === false ||
                $status === null ||
                !in_array($status, [0, 1], true)
            ) {
                respond(
                    false,
                    'Select a valid unit status.',
                    [],
                    422
                );
            }

            $existing = apiFetchOne(
                $db,
                'SELECT unit_id
                 FROM product_units
                 WHERE unit_id = :id
                   AND cid = :cid
                 LIMIT 1',
                [
                    ':id' => $id,
                    ':cid' => $cid
                ]
            );

            if ($existing === null) {
                respond(
                    false,
                    'Unit not found for this company.',
                    [],
                    404
                );
            }

            $duplicate = apiFetchOne(
                $db,
                'SELECT unit_id
                 FROM product_units
                 WHERE cid = :cid
                   AND unit_name = :name COLLATE NOCASE
                   AND unit_id != :id
                 LIMIT 1',
                [
                    ':cid' => $cid,
                    ':name' => $name,
                    ':id' => $id
                ]
            );

            if ($duplicate !== null) {
                respond(
                    false,
                    'Another unit already uses this name.',
                    [],
                    409
                );
            }

            $stmt = $db->prepare(
                'UPDATE product_units
                 SET
                    unit_name = :name,
                    unit_symbol = :symbol,
                    description = :description,
                    is_active = :status
                 WHERE unit_id = :id
                   AND cid = :cid'
            );

            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException(
                    'Unable to prepare unit update query.'
                );
            }

            try {
                $result = apiExecuteStatement($stmt, [
                    ':name' => $name,
                    ':symbol' => $symbol,
                    ':description' => $description,
                    ':status' => $status,
                    ':id' => $id,
                    ':cid' => $cid
                ]);

                $result->finalize();
            } finally {
                $stmt->close();
            }

            respond(true, 'Unit updated successfully.');

        // ----------------------------------------------
        // INVALID ACTION
        // ----------------------------------------------

        default:

            respond(
                false,
                'Unknown unit action.',
                [],
                400
            );
    }

} catch (Throwable $e) {

    error_log(
        'Product Units API error: ' . $e->getMessage()
    );

    respond(
        false,
        'A database error occurred while processing product units.',
        [],
        500
    );

} finally {

    if ($db instanceof SQLite3) {
        $db->close();
    }
}
