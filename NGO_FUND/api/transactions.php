<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust in production for security
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$dataFile = __DIR__ . '/../data/transactions.json';

// Ensure data directory and file exist
if (!is_dir(dirname($dataFile))) {
    mkdir(dirname($dataFile), 0755, true);
}
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, json_encode([]));
}

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function generateId() {
    return substr(bin2hex(random_bytes(4)), 0, 8); // 8-char random ID like JS version
}

// Load transactions
$transactions = json_decode(file_get_contents($dataFile), true);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        // Return all transactions
        sendResponse($transactions);
        break;

    case 'POST':
        // Add new transaction
        if (!$input || !isset($input['date'], $input['type'], $input['amount'])) {
            sendResponse(['error' => 'Missing required fields'], 400);
        }

        $newTransaction = [
            'id' => generateId(),
            'date' => $input['date'],
            'type' => $input['type'], // 'inflow' or 'outflow'
            'amount' => (float)$input['amount'],
            'category' => $input['category'] ?? 'Uncategorized',
            'description' => $input['description'] ?? '',
            'sourceOrBeneficiary' => $input['sourceOrBeneficiary'] ?? ''
        ];

        $transactions[] = $newTransaction;
        file_put_contents($dataFile, json_encode($transactions, JSON_PRETTY_PRINT));
        sendResponse($newTransaction, 201);
        break;

    case 'PUT':
        // Update transaction (expects id in URL like /api/transactions.php?id=abc123)
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendResponse(['error' => 'Transaction ID required'], 400);
        }

        $updated = false;
        foreach ($transactions as &$tx) {
            if ($tx['id'] === $id) {
                $tx['amount'] = isset($input['amount']) ? (float)$input['amount'] : $tx['amount'];
                $tx['category'] = $input['category'] ?? $tx['category'];
                $tx['description'] = $input['description'] ?? $tx['description'];
                $tx['sourceOrBeneficiary'] = $input['sourceOrBeneficiary'] ?? $tx['sourceOrBeneficiary'];
                // Add more fields here if needed
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            sendResponse(['error' => 'Transaction not found'], 404);
        }

        file_put_contents($dataFile, json_encode($transactions, JSON_PRETTY_PRINT));
        sendResponse($tx);
        break;

    case 'DELETE':
        // Delete transaction (expects id in URL)
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendResponse(['error' => 'Transaction ID required'], 400);
        }

        $originalCount = count($transactions);
        $transactions = array_filter($transactions, fn($tx) => $tx['id'] !== $id);

        if (count($transactions) === $originalCount) {
            sendResponse(['error' => 'Transaction not found'], 404);
        }

        file_put_contents($dataFile, json_encode(array_values($transactions), JSON_PRETTY_PRINT));
        sendResponse(['success' => true]);
        break;

    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}
?>
