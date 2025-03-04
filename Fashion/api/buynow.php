<?php
require_once('db.php');

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $object = new stdClass();
        $amount = 0;

        // ตรวจสอบว่ามีข้อมูล product ถูกส่งมาหรือไม่
        if (!isset($_POST['product'])) {
            $object->RespCode = 400;
            $object->RespMessage = 'bad: Missing product data';
            http_response_code(400);
            echo json_encode($object);
            exit();
        }

        $product = $_POST['product'];

        // ดึงข้อมูลสินค้าจากฐานข้อมูล
        $stmt = $db->prepare('SELECT id, price FROM sp_product ORDER BY id DESC');
        if ($stmt->execute()) {
            $queryproduct = array();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $items = array(
                    "id" => $row['id'],
                    "price" => $row['price']
                );
                array_push($queryproduct, $items);
            }

            // คำนวณยอดรวม
            for ($i = 0; $i < count($product); $i++) {
                for ($k = 0; $k < count($queryproduct); $k++) {
                    if (intval($product[$i]['id']) == intval($queryproduct[$k]['id'])) {
                        $amount += intval($product[$i]['count']) * intval($queryproduct[$k]['price']);
                        break;
                    }
                }
            }

            // คำนวณค่าส่งและ VAT
            $shiping = $amount + 60;
            $vat = $shiping * 7 / 100;
            $netamount = $shiping + $vat;
            $transid = microtime(true) * 1000;
            $product = json_encode($product);
            $mil = time() * 1000;
            $updated_at = date("Y-m-d h:i:sa");

            // บันทึกข้อมูลการสั่งซื้อ
            $stmt = $db->prepare('INSERT INTO sp_transaction (transid, orderlist, amount, shipping, vat, netamount, operation, mil, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt->execute([
                $transid, $product, $amount, $shiping, $vat, $netamount, 'PENDING', $mil, $updated_at
            ])) {
                $object->RespCode = 200;
                $object->RespMessage = 'success';
                $object->Amount = new stdClass();
                $object->Amount->Amount = $amount;
                $object->Amount->Shipping = $shiping;
                $object->Amount->Vat = $vat;
                $object->Amount->Netamount = $netamount;

                http_response_code(200);
            } else {
                $object->RespCode = 300;
                $object->log = 0;
                $object->RespMessage = 'bad: insert transaction fail';
                http_response_code(300);
            }
        } else {
            $object->RespCode = 500;
            $object->log = 1;
            $object->RespMessage = 'bad: cant get product';
            http_response_code(500);
        }
        echo json_encode($object);
    } else {
        http_response_code(405);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo $e->getMessage();
}
?>