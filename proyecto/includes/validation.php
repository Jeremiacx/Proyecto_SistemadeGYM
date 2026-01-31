<?php
/**
 * REGLA DE NEGOCIO COMPLEJA: Validación de límite de membresía
 * * Descripción: Al asignar una membresía con límite de visitas mensuales,
 * se valida que el miembro no haya excedido el límite en el mes actual.
 * * Lógica implementada:
 * 1. Verifica si la membresía tiene un límite de visitas por mes
 * 2. Cuenta las visitas del miembro en el mes actual
 * 3. Compara con el límite establecido
 * 4. Si se excede el límite, genera un error
 * 5. También valida que no haya pagos pendientes
 */

class Validation {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Valida el límite de visitas de una membresía
     * @param int $membership_id ID de la membresía
     * @param int $member_id ID del miembro (opcional, para actualizaciones)
     * @return string|null Mensaje de error o null si es válido
     */
    public function validateMembershipLimit($membership_id, $member_id = null) {
        try {
            // Obtener información de la membresía
            $query = "SELECT max_visits_per_month FROM membership_types WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$membership_id]);
            $membership = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Si la membresía no tiene límite, es válida
            if(!$membership || $membership['max_visits_per_month'] === null) {
                return null;
            }
            
            $max_visits = $membership['max_visits_per_month'];
            
            // Si es un miembro existente (actualización), contar sus visitas
            if($member_id) {
                $current_month_visits = $this->countMemberVisitsThisMonth($member_id);
                
                if($current_month_visits >= $max_visits) {
                    return "El miembro ya ha excedido el límite de $max_visits visitas este mes";
                }
            }
            
            // Validar que no haya pagos pendientes si es membresía pagada
            if($member_id) {
                $pending_payments = $this->checkPendingPayments($member_id);
                if($pending_payments > 0) {
                    return "El miembro tiene $pending_payments pagos pendientes";
                }
            }
            
            return null;
            
        } catch(PDOException $e) {
            error_log("Membership validation error: " . $e->getMessage());
            return "Error al validar la membresía";
        }
    }
    
    /**
     * Cuenta las visitas de un miembro en el mes actual
     */
    private function countMemberVisitsThisMonth($member_id) {
        $query = "SELECT COUNT(*) as count 
                FROM attendance 
                WHERE member_id = ? 
                AND MONTH(check_in) = MONTH(CURDATE()) 
                AND YEAR(check_in) = YEAR(CURDATE())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$member_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    /**
     * Verifica pagos pendientes de un miembro
     */
    private function checkPendingPayments($member_id) {
        $query = "SELECT COUNT(*) as count 
                FROM payments 
                WHERE member_id = ? 
                AND status IN ('pending', 'overdue') 
                AND due_date < CURDATE()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$member_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    /**
     * Valida la edad mínima requerida
     * @param string $dob Fecha de nacimiento (formato Y-m-d)
     * @param int $min_age Edad mínima
     * @return string|null Error o null
     */
    public function validateMinAge($dob, $min_age) {
        try {
            if (empty($dob)) {
                return "La fecha de nacimiento es obligatoria.";
            }

            $birthDate = new DateTime($dob);
            $today = new DateTime();
            
            // Calcula la diferencia exacta en años
            $age = $today->diff($birthDate)->y;
            
            if ($age < $min_age) {
                return "El miembro no cumple con la edad mínima. Tiene $age años, se requieren $min_age.";
            }
            
            return null;
        } catch (Exception $e) {
            return "Formato de fecha de nacimiento inválido.";
        }
    }
} 
?>