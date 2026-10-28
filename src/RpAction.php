<?php
namespace Rahul\Helpers;

use Exception;

class RpAction
{
    /**
     * Open MySQL Database Connection
     */
    public static function OpenCon($db = null)
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

        $database = $db;
        if (empty($database) && defined('db')) {
            $database = db;
        }
        if (empty($database)) {
            $database = $_ENV['DB_NAME'] ?? 'logistic_db';
        }

        $conn = mysqli_connect($host, $user, $pass, $database);

        if (!$conn) {
            throw new \Exception("Database Connection Failed: " . mysqli_connect_error());
        }

        mysqli_set_charset($conn, "utf8mb4");

        return $conn;
    }

    /**
     * Perform a secure database action using prepared statements.
     */
    public static function secureDbAction($table, $action, $data = [], $where = '', $params = [], $db = null)
    {
        $conn = self::OpenCon($db);

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return ['success' => false, 'error' => 'Invalid table name.'];
        }

        $sql = '';
        $types = '';
        $bindValues = [];

        try {
            switch ($action) {
                case 1: // INSERT
                    $columns = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
                    $placeholders = implode(', ', array_fill(0, count($data), '?'));
                    $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
                    $bindValues = array_values($data);
                    foreach ($bindValues as $value) {
                        $types .= self::getParamType($value);
                    }
                    break;

                case 2: // UPDATE
                    if (empty($where)) {
                        return ['success' => false, 'error' => 'Missing WHERE clause for update.'];
                    }
                    $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
                    $sql = "UPDATE `$table` SET $set WHERE $where";
                    $bindValues = array_merge(array_values($data), $params);
                    foreach ($bindValues as $value) {
                        $types .= self::getParamType($value);
                    }
                    break;

                case 3: // DELETE
                    if (empty($where)) {
                        return ['success' => false, 'error' => 'Missing WHERE clause for delete.'];
                    }
                    $sql = "DELETE FROM `$table` WHERE $where";
                    $bindValues = $params;
                    foreach ($bindValues as $value) {
                        $types .= self::getParamType($value);
                    }
                    break;

                case 4: // SELECT
                    $columns = $data['columns'] ?? '*';
                    $orderBy = $data['orderBy'] ?? '';
                    $limit = isset($data['limit']) ? (int) $data['limit'] : '';
                    $offset = isset($data['offset']) ? (int) $data['offset'] : null;

                    $columns = preg_replace('/[^\w\s,\`\*\(\)\-\+\=\<\>""\'\.]/', '', $columns);
                    $orderBy = preg_replace('/[^a-zA-Z0-9_,`\s]+/', '', $orderBy);

                    $sql = "SELECT $columns FROM `$table`";
                    if ($where)
                        $sql .= " WHERE $where";
                    if ($orderBy)
                        $sql .= " ORDER BY $orderBy";
                    if ($limit)
                        $sql .= " LIMIT $limit";
                    if ($offset !== null)
                        $sql .= " OFFSET $offset";

                    $bindValues = $params;
                    foreach ($bindValues as $value) {
                        $types .= self::getParamType($value);
                    }
                    break;

                case 5: // META
                    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
                    $dbName = $_ENV['DB_NAME'] ?? 'your_db_name';
                    $bindValues = [$dbName, $table];
                    foreach ($bindValues as $value) {
                        $types .= self::getParamType($value);
                    }
                    break;

                default:
                    return ['success' => false, 'error' => 'Invalid action code'];
            }

            $stmt = $conn->prepare($sql);
            if (!$stmt)
                throw new Exception($conn->error);

            if (!empty($bindValues)) {
                $stmt->bind_param($types, ...$bindValues);
            }

            $stmt->execute();
            $debugMode = isset($data['debug']) && $data['debug'] === true;

            if ($action === 1) {
                if ($stmt->affected_rows === 0 || $conn->insert_id === 0) {
                    return ['success' => false, 'error' => $conn->error ?: 'Insert failed', 'query' => $sql];
                }
                return ['success' => true, 'insert_id' => $conn->insert_id];
            }

            if ($action === 4) {
                $result = $stmt->get_result();
                $rows = $result->fetch_all(MYSQLI_ASSOC);
                return ['success' => true, 'data' => $rows, 'query' => $debugMode ? $sql : null];
            }

            if ($action === 5) {
                $result = $stmt->get_result();
                $cols = $result->fetch_all(MYSQLI_ASSOC);
                $fields = array_column($cols, 'COLUMN_NAME');
                return ['success' => true, 'data' => $fields];
            }

            return ['success' => true, 'affected_rows' => $stmt->affected_rows, 'query' => $sql . json_encode($bindValues)];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'query' => $sql];
        }
    }

    public static function getParamType($param)
    {
        if (is_int($param))
            return 'i';
        elseif (is_float($param))
            return 'd';
        else
            return 's';
    }

    public static function flattenStdClass($data)
    {
        if (is_object($data)) {
            $data = (array) $data;
        }
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::flattenStdClass($value);
            }
        }
        return $data;
    }

    public static function dbAction($table_name, $operation = 0, $form_data = "", $where_clause = "", $debug = 0, $db = null)
    {
        $conn = self::OpenCon($db);
        $dbActionStatus = [];

        if (empty($form_data) && $operation < 3) {
            $form_data = $_POST;
            array_pop($form_data);
        }

        if (!is_array($form_data)) {
            $form_data = [];
        }

        foreach ($form_data as $key => $val) {
            if (is_array($val) || is_object($val)) {
                $form_data[$key] = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
        }

        $fields = array_keys($form_data);
        $values = array_values($form_data);

        $whereSQL = '';
        if (!empty($where_clause)) {
            $whereSQL = strtoupper(substr(trim($where_clause), 0, 5)) === 'WHERE'
                ? " " . trim($where_clause)
                : " WHERE " . $where_clause;
        }

        $sql = '';
        $types = '';
        $bindValues = [];

        try {
            switch ($operation) {
                case 1:
                    $columns = implode(', ', array_map(fn($k) => "`$k`", $fields));
                    $placeholders = implode(', ', array_fill(0, count($values), '?'));
                    $sql = "INSERT INTO `$table_name` ($columns) VALUES ($placeholders)";
                    $bindValues = $values;
                    break;

                case 2:
                    if (empty($whereSQL)) {
                        return [0, 'Missing WHERE clause for update'];
                    }
                    $set = implode(', ', array_map(fn($k) => "`$k` = ?", $fields));
                    $sql = "UPDATE `$table_name` SET $set $whereSQL";
                    $bindValues = $values;
                    break;

                case 3:
                    if (empty($whereSQL)) {
                        return [0, 'Missing WHERE clause for delete'];
                    }
                    $sql = "DELETE FROM `$table_name` $whereSQL";
                    break;

                case 4:
                    $sql = "SELECT * FROM `$table_name` $whereSQL";
                    if ($debug) {
                        echo $sql . " DB:" . $db;
                    }
                    return mysqli_query($conn, $sql);

                case 5:
                    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
                    $bindValues = [$db, $table_name];
                    $types = 'ss';
                    $stmt = $conn->prepare($sql);
                    if (!$stmt)
                        throw new Exception($conn->error);
                    $stmt->bind_param($types, ...$bindValues);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $cols = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    return array_column($cols, 'COLUMN_NAME');
            }

            foreach ($bindValues as $val) {
                $types .= is_int($val) ? 'i' : (is_float($val) ? 'd' : 's');
            }

            if (in_array($operation, [1, 2, 3])) {
                $stmt = $conn->prepare($sql);
                if (!$stmt)
                    throw new Exception($conn->error);

                if (!empty($bindValues)) {
                    $stmt->bind_param($types, ...$bindValues);
                }
                $run = $stmt->execute();

                if ($debug) {
                    echo $sql . " DB:" . $db;
                }

                $dbActionStatus = $run ? [1, $sql] : [0, $stmt->error];
                $stmt->close();
                return $dbActionStatus;
            }
        } catch (Exception $e) {
            return [0, $e->getMessage()];
        }

        return [0, 'No valid operation matched'];
    }

    public static function safePost($key, $default = '', $sanitize = true)
    {
        if (!isset($_POST[$key]))
            return $default;
        $value = $_POST[$key];
        if ($sanitize && is_string($value)) {
            $value = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }

    public static function safeGet($key, $default = '', $sanitize = true)
    {
        if (!isset($_GET[$key]))
            return $default;
        $value = $_GET[$key];
        if ($sanitize && is_string($value)) {
            $value = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }

    public static function safePostInt($key, $default = 0)
    {
        return isset($_POST[$key]) ? intval($_POST[$key]) : $default;
    }

    public static function safeGetInt($key, $default = 0)
    {
        return isset($_GET[$key]) ? intval($_GET[$key]) : $default;
    }

    public static function safePostBool($key)
    {
        return isset($_POST[$key]) && ($_POST[$key] === '1' || $_POST[$key] === 'true');
    }

    public static function safeGetBool($key)
    {
        return isset($_GET[$key]) && ($_GET[$key] === '1' || $_GET[$key] === 'true');
    }

    public static function redirectTo($path = '', $delay = 0, $message = '', $errortype = 'success')
    {
        $script = basename($_SERVER['SCRIPT_NAME']);
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

        if ($path === '') {
            $url = $base;
        } elseif (strpos($path, '?') === 0) {
            $url = $base . '/' . $script . $path;
        } else {
            $url = $base . '/' . ltrim($path, '/');
        }

        if (!headers_sent()) {
            if ($delay > 0) {
                header("Refresh: $delay; URL=$url");
            } else {
                header("Location: $url");
            }
            exit;
        }

        echo "<script>
            setTimeout(() => {
                window.location.href = '{$url}';
            }, " . ($delay * 1000) . ");
        </script>";
        exit;
    }

    public static function filterThis($string, $db = null)
    {
        $conn = self::OpenCon($db);
        if (is_string($string)) {
            return @mysqli_real_escape_string($conn, $string);
        }
    }

    public static function array_map_deep($array, $callback)
    {
        $new = array();
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $new[$key] = self::array_map_deep($val, $callback);
            } else {
                $new[$key] = call_user_func($callback, $val);
            }
        }
        return $new;
    }

    public static function is_multi($array)
    {
        $rv = array_filter($array, 'is_array');
        return count($rv) > 0;
    }

    public static function queryRun($query, $debug = 0, $db = null)
    {
        $conn = self::OpenCon($db);

        if ($debug == 1) {
            print $query . " DB:" . $db;
        }

        if ($debug == 3) {
            $result = $conn->query($query);
            if ($result == false) {
                echo "Error: " . mysqli_error($conn);
            }
            return $result;
        }
    }

    public static function isAdmin()
    {
        if (isset($_SESSION['role'])) {
            return ($_SESSION['role'] >= 0 && $_SESSION['role'] <= 3);
        }
        return false;
    }

    public static function isUser($action = '')
    {
        if (!empty($action) && $action === 'location') {
            $userId = defined('user_id') ? user_id : ($_SESSION['user_id'] ?? 0);
            $storeId = defined('organizationId') ? organizationId : ($_SESSION['store'] ?? 0);

            $getUserBranch = self::getRow("user_locations", '', '', "WHERE user_id = $userId AND store = $storeId", 2);

            if (!empty($getUserBranch['location_id'])) {
                return $getUserBranch['location_id'];
            }
            return null;
        }

        return isset($_SESSION['role']) && $_SESSION['role'] > 3;
    }

    public static function checkUser()
    {
        return isset($_SESSION['role']) && $_SESSION['role'] > 7;
    }

    public static function isShop()
    {
        return isset($_SESSION['role']) && $_SESSION['role'] >= 0;
    }

    public static function isSuper()
    {
        return isset($_SESSION['role']) && $_SESSION['role'] == 1;
    }

    public static function getName($table, $id, $type = 0, $name = 'name', $id_name = '', $db = null)
    {
        $conn = self::OpenCon($db);
        $query = "SELECT * FROM $table where id=" . $id;
        if ($table == 'groups') {
            $query = "SELECT * FROM $table where group_id=" . $id;
        }
        if ($table == 'customers') {
            $query = "SELECT * FROM $table where contact_id='" . $id . "'";
        }
        if (!empty($id_name)) {
            $query = "SELECT * FROM $table where $id_name='" . $id . "'";
        }
        $name_result = $conn->query($query);

        if ($name_result && $name_result->num_rows > 0) {
            $row = $name_result->fetch_assoc();
            if ($type == 0) {
                echo $row[$name];
            } elseif ($type == 1) {
                return $row[$name];
            } elseif ($type == 2) {
                return $row;
            }
        }
        return null;
    }

    public static function getRow($table, $id = '', $name = '', $where_clause = '', $type = 0, $db = null)
    {
        $conn = self::OpenCon($db);
        $query = '';

        if (!empty($where_clause)) {
            $query = "SELECT * FROM $table " . trim($where_clause);
        } elseif (!empty($id)) {
            if ($table === 'profile') {
                $query = "SELECT * FROM $table WHERE u_id = " . (int) $id;
            } else {
                $query = "SELECT * FROM $table WHERE id = " . (int) $id;
            }
        } else {
            return null;
        }

        $name_result = $conn->query($query);
        if ($name_result && $name_result->num_rows > 0) {
            $row = $name_result->fetch_assoc();
            if ($type === 0 && isset($row[$name])) {
                echo $row[$name];
            } elseif ($type === 1 && isset($row[$name])) {
                return $row[$name];
            } elseif ($type === 2) {
                return $row;
            }
        }
        return null;
    }

    public static function getRowById($table, $id, $column = 'id', $db = null)
    {
        $where = "$column = ?";
        $params = [$id];
        $result = self::secureDbAction($table, 4, [], $where, $params, $db);
        return (isset($result['data'][0])) ? $result['data'][0] : null;
    }

    public static function lastId($table_name, $db = null)
    {
        $conn = self::OpenCon($db);
        $query = "SELECT id FROM $table_name ORDER BY id DESC LIMIT 1";
        $result = mysqli_query($conn, $query);
        if (!$result) {
            die('Could not query:' . mysqli_error($conn));
        }
        return mysqli_fetch_assoc($result);
    }

    public static function table_sum($table, $col, $limits = '', $db = null)
    {
        $conn = self::OpenCon($db);
        $sum_q = "SELECT *, SUM($col) AS Total FROM $table WHERE MONTH(date) = MONTH(CURDATE()) AND status=1 ORDER BY Total DESC";
        if ($limits != "") {
            $sum_q .= " LIMIT " . $limits;
        }
        return mysqli_query($conn, $sum_q);
    }

    public static function progress($value, $max, $type = 0)
    {
        $percent = (!empty($max)) ? ($value * 100) / $max : 0;
        if ($percent > 100)
            $percent = 100;

        $formatted = number_format($percent, 2);
        if ($type) {
            return $formatted;
        }
        echo $formatted;
    }

    public static function colSum($table, $col, $where_clause = '', $db = null)
    {
        $conn = self::OpenCon($db);
        $sum_q = "SELECT SUM($col) AS Total FROM $table";
        if ($where_clause != "") {
            $sum_q .= " WHERE " . $where_clause;
        }
        return mysqli_query($conn, $sum_q);
    }

    public static function getTotal($table, $col, $where_clause = '', $db = null)
    {
        $Total = 0;
        $query = "SELECT SUM($col) AS Total FROM $table";
        if ($where_clause != "") {
            $query .= " WHERE " . $where_clause;
        }
        $excute = self::queryRun($query, 3, $db);
        if ($excute && $excute->num_rows > 0) {
            $tot = $excute->fetch_assoc();
            if (isset($tot['Total'])) {
                $Total = $tot['Total'];
            }
        }
        return $Total;
    }

    public static function isPermMode($role, $ModuleKey)
    {
        $perFetch = self::dbAction('permission_role', 4, '', 'role_id=' . $role);
        if ($perFetch && $perFetch->num_rows > 0) {
            $isPerm = array();
            foreach ($perFetch as $permData) {
                $permName = self::getRow('permissions', $permData['permission_id'], 'name', '', 1);
                if (!empty($permName)) {
                    $fileName = strtolower($permName);
                    $first_name = explode("-", $fileName);
                    if (isset($first_name[0])) {
                        $fileName = $first_name[0];
                    }
                    if (strtolower($fileName) == strtolower($ModuleKey)) {
                        $isPerm[] = ($permData['isRead'] == 1) ? "1" : "0";
                        $isPerm[] = ($permData['isWrite'] == 1) ? "1" : "0";
                        $isPerm[] = ($permData['isEdit'] == 1) ? "1" : "0";
                        $isPerm[] = ($permData['isDelete'] == 1) ? "1" : "0";
                    }
                }
            }
            return $isPerm;
        }
        return [];
    }

    public static function mgs($mgs = 'Action Performed Successfully!', $key = 1)
    {
        if ($key) {
            echo '<div class="alert alert-success alert-dismissible text-sm"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button><i class="icon fa fa-check"></i> ' . $mgs . '</div>';
        } else {
            echo '<div class="alert alert-danger alert-dismissible text-sm"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button><i class="icon fa fa-ban"></i> ' . $mgs . '</div>';
        }
    }

    public static function alert($mgs = 'Action Performed Successfully!', $key = 1)
    {
        $icon = $key ? "success" : "error";
        echo "<script>boom('" . addslashes($mgs) . "', '$icon', true);</script>";
    }

    public static function isModCheck($name)
    {
        $name = strtolower($name);
        $first_name = explode("-", $name);
        if (isset($first_name[0])) {
            $name = $first_name[0];
        }
        if ($name != 'permissions') {
            $query = "SELECT * from permissions where name='" . $name . "' AND status=1";
            $check_mod = self::queryRun($query, 3);
            if ($check_mod && $check_mod->num_rows > 0) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }

    public static function renderDropdown($name, $table, $title = '', $selectedId = 0, $options = [])
    {
        $valCol = $options['val_col'] ?? 'id';
        $textCol = $options['text_col'] ?? 'name';
        $class = $options['class'] ?? 'form-control select2bs4';
        $wrapper = $options['wrapper'] ?? true;
        $req = !empty($options['required']) ? 'required' : '';
        $storeId = defined('organizationId') ? intval(organizationId) : 0;
        $showAddBtn = $options['add_modal'] ?? true;
        $where = $options['where'] ?? "status=1";

        if (!isset($options['use_store']) || $options['use_store'] === true) {
            if ($storeId)
                $where .= " AND store=$storeId";
        }

        if ($table === 'roles' && !empty($options['filter_roles'])) {
            $where .= ' AND id>2';
        }

        if (!empty($options['group_by'])) {
            $dbConn = $options['db'] ?? null;
            $query = "SELECT * FROM $table WHERE $where GROUP BY " . $options['group_by'];
            $rows = self::queryRun($query, 3, $dbConn);
        } else {
            $rows = self::dbAction($table, 4, '', $where, 0);
        }

        $html = '';
        if ($wrapper) {
            $html .= '<div class="form-group mb-3 dynamicselect">';
        }

        if (!empty($title)) {
            $html .= "<label class='form-label text-muted'>$title</label>";
        }

        if ($showAddBtn) {
            $html .= '<div class="input-group d-flex align-items-center">';
        }

        $disabled = !empty($options['disabled']) ? 'disabled' : '';
        $html .= "<select class='$class flex-grow-1' name='$name' id='$name' $req $disabled>";

        $defaultText = !empty($title) ? "--$title--" : "--Select Option--";
        $html .= "<option value=''>$defaultText</option>";

        if ($rows) {
            foreach ($rows as $r) {
                $val = $r[$valCol] ?? '';
                $text = $r[$textCol] ?? '';
                $keynames = self::renderColName($table);
                $val = $r[$keynames[0]] ?? $valCol;
                $text = $r[$keynames[1]] ?? $textCol;
                $sel = ($val !== '' && $val == $selectedId) ? ' selected' : '';

                $attr = '';
                if (isset($r['user_id'])) {
                    $attr .= " data-user_id='{$r['user_id']}'";
                }

                $html .= "<option value='$val'$sel$attr>$text</option>";
            }
        }

        $html .= "</select>";
        if ($showAddBtn) {
            $html .= "<button type='button' class='btn btn-light quick-add-btn' data-bs-toggle='offcanvas' data-bs-target='#crudCanvas' data-table='" . $table . "' data-select-id='" . $name . "' data-title='" . $title . "' title='Add New " . $title . "' style='padding: 6px 10px'><i class='fa fa-plus'></i></button>";
            $html .= '</div>';
        }
        if ($wrapper) {
            $html .= "</div>";
        }

        echo $html;
    }

    public static function renderColName($table, $colId = 'id', $colName = 'name')
    {
        return [$colId, $colName];
    }
}