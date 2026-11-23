<?php
function fulfillPaidIntent(mysqli $conn, int $paymentId): bool
{
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT * FROM payment_transactions WHERE payment_id = ? FOR UPDATE');
        $stmt->bind_param('i', $paymentId);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$payment) {
            $conn->rollback();
            return false;
        }
        if ($payment['status'] === 'paid') {
            $conn->commit();
            return true;
        }
        if ($payment['status'] !== 'pending') {
            $conn->commit();
            return false;
        }

        $payload = json_decode($payment['payload'], true, 512, JSON_THROW_ON_ERROR);
        $userId = (int) $payment['user_id'];
        if ($payment['payment_type'] === 'order') {
            $itemId = (int) ($payload['item_id'] ?? 0);
            $quantity = (int) ($payload['quantity'] ?? 0);
            if ($itemId < 1 || $quantity < 1) {
                throw new RuntimeException('The saved order details are invalid.');
            }

            $stock = $conn->prepare('UPDATE inventory SET quantity_in_stock = quantity_in_stock - ? WHERE item_id = ? AND quantity_in_stock >= ?');
            $stock->bind_param('iii', $quantity, $itemId, $quantity);
            $stock->execute();
            if ($stock->affected_rows !== 1) {
                $stock->close();
                $conn->rollback();
                return false;
            }
            $stock->close();

            $totalAmount = number_format((float) $payment['amount'], 2, '.', '');
            $orderStatus = 'pending';
            $paymentStatus = 'paid';
            $paymentMethod = 'mpesa';
            $deliveryAddress = trim((string) ($payload['delivery_address'] ?? ''));
            $orderStatus = 'pending';
            $dispatchStatus = 'processing';
            $order = $conn->prepare('INSERT INTO orders (farmer_id, item_id, quantity, total_amount, payment_status, status, payment_method, order_status, delivery_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $order->bind_param('iiidsssss', $userId, $itemId, $quantity, $totalAmount, $paymentStatus, $orderStatus, $paymentMethod, $dispatchStatus, $deliveryAddress);
            if (!$order->execute()) {
                $order->close();
                throw new RuntimeException('Could not record the paid order.');
            }
            $order->close();
        } elseif ($payment['payment_type'] === 'booking') {
            $serviceType = (string) ($payload['service_type'] ?? '');
            $bookingDate = (string) ($payload['booking_date'] ?? '');
            if ($serviceType === '' || $bookingDate === '') {
                throw new RuntimeException('The saved booking details are invalid.');
            }

            $notes = (string) ($payload['notes'] ?? '');
            $isCertifiable = !empty($payload['is_certifiable']) ? 1 : 0;
            $totalAmount = number_format((int) $payment['amount'], 2, '.', '');
            $bookingStatus = 'pending';
            $paymentStatus = 'paid';
            $paymentMethod = 'mpesa';
            $booking = $conn->prepare('INSERT INTO bookings (farmer_id, service_type, booking_date, status, payment_method, notes, total_amount, payment_status, is_certifiable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $booking->bind_param('isssssdsi', $userId, $serviceType, $bookingDate, $bookingStatus, $paymentMethod, $notes, $totalAmount, $paymentStatus, $isCertifiable);
            if (!$booking->execute()) {
                $booking->close();
                throw new RuntimeException('Could not record the paid booking.');
            }
            $booking->close();
        } else {
            throw new RuntimeException('The saved payment type is invalid.');
        }

        $update = $conn->prepare("UPDATE payment_transactions SET status = 'paid' WHERE payment_id = ?");
        $update->bind_param('i', $paymentId);
        $update->execute();
        $update->close();
        $conn->commit();
        return true;
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Payment fulfillment error: ' . $error->getMessage());
        return false;
    }
}

function reconcilePaidIntent(mysqli $conn, int $paymentId): bool
{
    $stmt = $conn->prepare("UPDATE payment_transactions SET status = 'pending' WHERE payment_id = ? AND status = 'paid_unfulfilled'");
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $reopened = $stmt->affected_rows === 1;
    $stmt->close();

    if (!$reopened) {
        return false;
    }

    if (fulfillPaidIntent($conn, $paymentId)) {
        return true;
    }

    $stmt = $conn->prepare("UPDATE payment_transactions SET status = 'paid_unfulfilled' WHERE payment_id = ? AND status = 'pending'");
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $stmt->close();
    return false;
}