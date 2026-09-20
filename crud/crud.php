<?php
include_once "../core/config.php";

$action = $_POST['action'] ?? '';
$table = $_POST['table'] ?? '';
$id = intval($_POST['id'] ?? 0);
$data = $_POST['data'] ?? [];

switch ($action) {
  case 'save':
    if (!empty($data['id'])) {
      $id = intval($data['id']);
      unset($data['id']);
      $res = secureDbAction($table, 2, $data, "id = ?", [$id]);

      echo json_encode(
        $res['success']
        ? ['status' => 'success', 'message' => 'Updated', 'id' => $id]
        : ['status' => 'error', 'message' => $res['error'] ?? 'Update failed']
      );
    } else {
      $res = secureDbAction($table, 1, $data);
      echo json_encode($res);
      // echo json_encode(
      //   $res['success']
      //     ? ['status' => 'success', 'message' => 'Inserted', 'id' => $res['insert_id'] ?? 0]
      //     : ['status' => 'error', 'message' => $res['error'] ?? 'Insert failed']
      // );
    }
    break;

  case 'delete':
    $id = intval($_POST['id'] ?? 0);
    $res = secureDbAction($table, 3, [], "id = ?", [$id]);

    echo json_encode(
      $res['success']
      ? ['status' => 'success', 'message' => 'Deleted']
      : ['status' => 'error', 'message' => $res['error'] ?? 'Delete failed']
    );
    break;

  case 'bulk-delete':
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) {
      echo json_encode(['status' => 'error', 'message' => 'No IDs provided']);
      break;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $res = secureDbAction($table, 3, [], "id IN ($placeholders)", $ids);

    echo json_encode(
      $res['success']
      ? ['status' => 'success', 'message' => 'Deleted ' . count($ids) . ' records']
      : ['status' => 'error', 'message' => $res['error'] ?? 'Bulk delete failed']
    );
    break;

  case 'bulk-status':
    $ids = $_POST['ids'] ?? [];
    $status = intval($_POST['status'] ?? 0);

    if (!is_array($ids) || empty($ids)) {
      echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
      break;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $res = secureDbAction($table, 2, ['status' => $status], "id IN ($placeholders)", $ids);

    echo json_encode(
      $res['success']
      ? ['status' => 'success', 'message' => ($status ? 'Unblocked' : 'Blocked') . " " . implode(',', $ids)]
      : ['status' => 'error', 'message' => $res['error'] ?? 'Bulk status update failed']
    );
    break;
}