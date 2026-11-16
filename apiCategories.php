<?php

declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';



// --------------------------------------------------
// 1. JSON RESPONSE HELPER
// --------------------------------------------------

function respond(bool $success, string $message, array $data = [], int $status = 200): void
{
    apiRespond($success ? 'success' : 'error', $message, $data, $status);
}

// --------------------------------------------------
// 2. SQLITE3 STATEMENT HELPER
// --------------------------------------------------

/**
 * Bind parameters and execute an SQLite3 prepared statement.
 *
 * SQLite3Stmt::execute() does not accept a parameter array.
 */
$cid = (int) $_SESSION['cid'];

$action = apiInput('action');

// --------------------------------------------------
// 4. DATABASE CONNECTION
// --------------------------------------------------

$db = null;

try {
    // Your existing HopWeb SQLite3 connection.
    $db = stockKeeperOpenDatabase();

    // Enable SQLite exceptions where supported.
    if (method_exists($db, 'enableExceptions')) {
        $db->enableExceptions(true);
    }

    // --------------------------------------------------
    // 5. CATEGORY ACTIONS
    // --------------------------------------------------

    switch ($action) {

        // ----------------------------------------------
        // LIST CATEGORIES
        // ----------------------------------------------

        case 'list':

            $stmt = $db->prepare(
                'SELECT
                    category_id,
                    category_name,
                    category_description,
                    is_active,
                    created_at
                 FROM product_categories
                 WHERE cid = :cid
                 ORDER BY category_name COLLATE NOCASE ASC'
            );

            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException(
                    'Unable to prepare category list query.'
                );
            }

            try {
                $result = apiExecuteStatement($stmt, [
                    ':cid' => $cid
                ]);

                $categories = [];

                try {
                    while (
                        ($row = $result->fetchArray(SQLITE3_ASSOC))
                        !== false
                    ) {
                        $categories[] = $row;
                    }
                } finally {
                    $result->finalize();
                }
            } finally {
                $stmt->close();
            }

            respond(
                true,
                'Categories loaded successfully.',
                ['categories' => $categories]
            );

        // ----------------------------------------------
        // CREATE CATEGORY
        // ----------------------------------------------

        case 'create':

            $name = apiInput('category_name');
            $description = apiInput('category_description');

            if ($name === '') {
                respond(false, 'Enter a category name.', [], 422);
            }

            if (apiTextLength($name) > 150) {
                respond(
                    false,
                    'Category name must not exceed 150 characters.',
                    [],
                    422
                );
            }

            if (apiTextLength($description) > 1000) {
                respond(
                    false,
                    'Description must not exceed 1000 characters.',
                    [],
                    422
                );
            }

            // Prevent duplicate category names for this company.
            $duplicate = apiFetchOne(
                $db,
                'SELECT category_id
                 FROM product_categories
                 WHERE cid = :cid
                   AND category_name = :name COLLATE NOCASE
                 LIMIT 1',
                [
                    ':cid' => $cid,
                    ':name' => $name
                ]
            );

            if ($duplicate !== null) {
                respond(
                    false,
                    'A category with this name already exists.',
                    [],
                    409
                );
            }

            $stmt = $db->prepare(
                'INSERT INTO product_categories
                    (
                        cid,
                        category_name,
                        category_description,
                        is_active,
                        created_at
                    )
                 VALUES
                    (
                        :cid,
                        :name,
                        :description,
                        1,
                        CURRENT_TIMESTAMP
                    )'
            );

            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException(
                    'Unable to prepare category insert query.'
                );
            }

            try {
                $result = apiExecuteStatement($stmt, [
                    ':cid' => $cid,
                    ':name' => $name,
                    ':description' => $description
                ]);

                $result->finalize();
            } finally {
                $stmt->close();
            }

            respond(
                true,
                'Category added successfully.',
                [
                    'category_id' => (int) $db->lastInsertRowID()
                ]
            );

        // ----------------------------------------------
        // UPDATE CATEGORY
        // ----------------------------------------------

        case 'update':

            $id = filter_var(
                $_POST['category_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $name = apiInput('category_name');
            $description = apiInput('category_description');

            $status = filter_var(
                $_POST['status'] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($id === false || $id === null || $id < 1) {
                respond(
                    false,
                    'Invalid category selected.',
                    [],
                    422
                );
            }

            if ($name === '') {
                respond(false, 'Enter a category name.', [], 422);
            }

            if (apiTextLength($name) > 150) {
                respond(
                    false,
                    'Category name must not exceed 150 characters.',
                    [],
                    422
                );
            }

            if (apiTextLength($description) > 1000) {
                respond(
                    false,
                    'Description must not exceed 1000 characters.',
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
                    'Select a valid category status.',
                    [],
                    422
                );
            }

            // Confirm the category belongs to this company.
            $existing = apiFetchOne(
                $db,
                'SELECT category_id
                 FROM product_categories
                 WHERE category_id = :id
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
                    'Category not found for this company.',
                    [],
                    404
                );
            }

            // Prevent duplicate names, excluding this category.
            $duplicate = apiFetchOne(
                $db,
                'SELECT category_id
                 FROM product_categories
                 WHERE cid = :cid
                   AND category_name = :name COLLATE NOCASE
                   AND category_id != :id
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
                    'Another category already uses this name.',
                    [],
                    409
                );
            }

            $stmt = $db->prepare(
                'UPDATE product_categories
                 SET
                    category_name = :name,
                    category_description = :description,
                    is_active = :status
                 WHERE category_id = :id
                   AND cid = :cid'
            );

            if (!$stmt instanceof SQLite3Stmt) {
                throw new RuntimeException(
                    'Unable to prepare category update query.'
                );
            }

            try {
                $result = apiExecuteStatement($stmt, [
                    ':name' => $name,
                    ':description' => $description,
                    ':status' => $status,
                    ':id' => $id,
                    ':cid' => $cid
                ]);

                $result->finalize();
            } finally {
                $stmt->close();
            }

            respond(
                true,
                'Category updated successfully.'
            );

        // ----------------------------------------------
        // UNKNOWN ACTION
        // ----------------------------------------------

        default:

            respond(
                false,
                'Unknown category action.',
                [],
                400
            );
    }

} catch (Throwable $e) {

    error_log(
        'Category API error: ' . $e->getMessage()
    );

    // Keep technical database details out of user-facing responses.
    respond(
        false,
        'A database error occurred while processing categories.',
        [],
        500
    );

} finally {

    if ($db instanceof SQLite3) {
        $db->close();
    }
}
