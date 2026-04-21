<?php

namespace EMA\Models;

use EMA\Config\Database;
use EMA\Utils\Security;
use EMA\Utils\Logger;

class User
{
    private ?int $id = null;
    private string $fullName;
    private ?string $image;
    private string $email;
    private string $phone;
    private string $password;
    private ?string $createdAt;
    private string $role;
    private ?int $isLoggedIn;

    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    private function fill(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->fullName = $data['full_name'] ?? '';
        $this->image = $data['image'] ?? null;
        $this->email = $data['email'] ?? '';
        $this->phone = $data['phone'] ?? '';
        $this->password = $data['password'] ?? '';
        $this->createdAt = $data['created_at'] ?? null;
        $this->role = $data['role'] ?? 'user';
        $this->isLoggedIn = $data['is_logged_in'] ?? null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): void
    {
        $this->fullName = $fullName;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function toArray(bool $includePassword = false): array
    {
        $data = [
            'id' => $this->id,
            'full_name' => $this->fullName,
            'image' => $this->image,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'created_at' => $this->createdAt,
        ];

        if ($includePassword) {
            $data['password'] = $this->password;
        }

        return $data;
    }

    public static function findByEmail(string $email): ?self
    {
        try {
            $stmt = Database::prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                return new self($row);
            }

            return null;
        } catch (\Exception $e) {
            Logger::error('Error finding user by email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public static function findById(int $id): ?self
    {
        try {
            $stmt = Database::prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                return new self($row);
            }

            return null;
        } catch (\Exception $e) {
            Logger::error('Error finding user by ID', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public static function create(array $data): self
    {
        try {
            $hashedPassword = Security::hashPassword($data['password']);

            $stmt = Database::prepare(
                "INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)"
            );

            $role = $data['role'] ?? 'user';

            $stmt->bind_param(
                'sssss',
                $data['full_name'],
                $data['email'],
                $data['phone'],
                $hashedPassword,
                $role
            );

            $stmt->execute();

            $userId = Database::insertId();
            Logger::info('User created', [
                'user_id' => $userId,
                'email' => $data['email']
            ]);

            return self::findById($userId);
        } catch (\Exception $e) {
            Logger::error('Error creating user', [
                'email' => $data['email'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public static function update(int $id, array $data): bool
    {
        try {
            $fields = [];
            $types = '';
            $values = [];

            foreach ($data as $key => $value) {
                if ($key === 'password') {
                    $fields[] = 'password = ?';
                    $types .= 's';
                    $values[] = Security::hashPassword($value);
                } elseif (in_array($key, ['full_name', 'email', 'phone', 'role', 'image'])) {
                    $fields[] = "$key = ?";
                    $types .= 's';
                    $values[] = $value;
                }
            }

            if (empty($fields)) {
                return false;
            }

            $fields[] = 'id = ?';
            $types .= 'i';
            $values[] = $id;

            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = Database::prepare($sql);
            $stmt->bind_param($types, ...$values);

            $result = $stmt->execute();

            if ($result) {
                Logger::info('User updated', ['user_id' => $id]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function delete(int $id): bool
    {
        try {
            $stmt = Database::prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $id);
            $result = $stmt->execute();

            if ($result) {
                Logger::info('User deleted', ['user_id' => $id]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting user', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function isEmailExists(string $email): bool
    {
        try {
            $stmt = Database::prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();

            return $stmt->num_rows > 0;
        } catch (\Exception $e) {
            Logger::error('Error checking email existence', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function isPhoneExists(string $phone): bool
    {
        try {
            $stmt = Database::prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            $stmt->store_result();

            return $stmt->num_rows > 0;
        } catch (\Exception $e) {
            Logger::error('Error checking phone existence', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return Security::verifyPassword($password, $hash);
    }

    public static function updateLoginTime(int $id): bool
    {
        try {
            $stmt = Database::prepare("UPDATE users SET is_logged_in = 1 WHERE id = ?");
            $stmt->bind_param('i', $id);
            return $stmt->execute();
        } catch (\Exception $e) {
            Logger::error('Error updating login time', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function getAllAdmins(): array
    {
        try {
            $sql = "SELECT au.*, u.full_name, u.email, u.phone, u.role, u.image, u.created_at, u.is_logged_in
                     FROM admin_users au
                     JOIN users u ON au.user_id = u.id
                     ORDER BY au.assigned_at DESC";
            $stmt = Database::prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();

            $admins = [];
            while ($row = $result->fetch_assoc()) {
                $admins[] = $row;
            }

            return $admins;
        } catch (\Exception $e) {
            Logger::error('Error getting all admins', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public static function grantAdmin(int $userId, string $grantedBy): bool
    {
        try {
            // Check if user exists
            $user = self::findById($userId);
            if (!$user) {
                return false;
            }

            // Check if user is already admin
            if (self::isAdmin($userId)) {
                return false;
            }

            // Start transaction
            Database::beginTransaction();

            // Insert into admin_users
            $stmt1 = Database::prepare(
                "INSERT INTO admin_users (user_id, full_name, email, assigned_at) VALUES (?, ?, ?, NOW())"
            );
            $stmt1->bind_param('iss', $userId, $user->getFullName(), $user->getEmail());
            $stmt1->execute();

            // Update user role
            $stmt2 = Database::prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $stmt2->bind_param('i', $userId);
            $stmt2->execute();

            // Commit transaction
            Database::commit();

            Logger::logSecurityEvent('Admin privileges granted', [
                'user_id' => $userId,
                'email' => $user->getEmail(),
                'granted_by' => $grantedBy
            ]);

            return true;
        } catch (\Exception $e) {
            // Rollback on any error
            Database::rollback();
            Logger::error('Error granting admin privileges', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function revokeAdmin(int $userId): bool
    {
        try {
            // Check if user is admin
            if (!self::isAdmin($userId)) {
                return false;
            }

            // Start transaction
            Database::beginTransaction();

            // Delete from admin_users
            $stmt1 = Database::prepare("DELETE FROM admin_users WHERE user_id = ?");
            $stmt1->bind_param('i', $userId);
            $stmt1->execute();

            // Update user role
            $stmt2 = Database::prepare("UPDATE users SET role = 'user' WHERE id = ?");
            $stmt2->bind_param('i', $userId);
            $stmt2->execute();

            // Commit transaction
            Database::commit();

            Logger::logSecurityEvent('Admin privileges revoked', [
                'user_id' => $userId,
                'revoked_by' => $_SESSION['user_id'] ?? null
            ]);

            return true;
        } catch (\Exception $e) {
            // Rollback on any error
            Database::rollback();
            Logger::error('Error revoking admin privileges', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function isAdminById(int $userId): bool
    {
        try {
            $stmt = Database::prepare("SELECT id FROM admin_users WHERE user_id = ? LIMIT 1");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->store_result();

            return $stmt->num_rows > 0;
        } catch (\Exception $e) {
            Logger::error('Error checking admin status', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function getUserStats(): array
    {
        try {
            $stats = [];

            // Total users
            $stmt1 = Database::prepare("SELECT COUNT(*) as total FROM users");
            $stmt1->execute();
            $stats['total_users'] = (int) $stmt1->get_result()->fetch_assoc()['total'];

            // Active users (logged in)
            $stmt2 = Database::prepare("SELECT COUNT(*) as active FROM users WHERE is_logged_in = 1");
            $stmt2->execute();
            $stats['active_users'] = (int) $stmt2->get_result()->fetch_assoc()['active'];

            // New users this month
            $stmt3 = Database::prepare("SELECT COUNT(*) as new_users FROM users WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
            $stmt3->execute();
            $stats['new_users_this_month'] = (int) $stmt3->get_result()->fetch_assoc()['new_users'];

            // User roles distribution
            $stmt4 = Database::prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
            $stmt4->execute();
            $result4 = $stmt4->get_result();

            $stats['user_roles'] = ['user' => 0, 'admin' => 0];
            while ($row = $result4->fetch_assoc()) {
                $stats['user_roles'][$row['role']] = (int) $row['count'];
            }

            return $stats;
        } catch (\Exception $e) {
            Logger::error('Error getting user stats', [
                'error' => $e->getMessage()
            ]);
            return [
                'total_users' => 0,
                'active_users' => 0,
                'new_users_this_month' => 0,
                'user_roles' => ['user' => 0, 'admin' => 0]
            ];
        }
    }

    public static function deleteUserCascade(int $userId): bool
    {
        try {
            // Start transaction
            Database::beginTransaction();

            // Delete from admin_users first (foreign key dependency)
            $stmt1 = Database::prepare("DELETE FROM admin_users WHERE user_id = ?");
            $stmt1->bind_param('i', $userId);
            $stmt1->execute();

            // Delete from access_permissions
            $stmt2 = Database::prepare("DELETE FROM access_permissions WHERE identifier = ?");
            $identifier = 'user_' . $userId;
            $stmt2->bind_param('s', $identifier);
            $stmt2->execute();

            // Delete from password_reset_requests
            $stmt3 = Database::prepare("DELETE FROM password_reset_requests WHERE user_id = ?");
            $stmt3->bind_param('i', $userId);
            $stmt3->execute();

            // Delete from users table
            $stmt4 = Database::prepare("DELETE FROM users WHERE id = ?");
            $stmt4->bind_param('i', $userId);
            $result = $stmt4->execute();

            // Commit transaction
            Database::commit();

            if ($result) {
                Logger::logSecurityEvent('User deleted with cascade cleanup', [
                    'user_id' => $userId,
                    'admin_id' => $_SESSION['user_id'] ?? null
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            // Rollback on any error
            Database::rollback();
            Logger::error('Error deleting user with cascade', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function updateLogoutTime(int $id): bool
    {
        try {
            $stmt = Database::prepare("UPDATE users SET is_logged_in = 0 WHERE id = ?");
            $stmt->bind_param('i', $id);
            return $stmt->execute();
        } catch (\Exception $e) {
            Logger::error('Error updating logout time', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public static function getAllUsers(
        int $page = 1,
        int $perPage = 20,
        string $search = null,
        string $role = null,
        string $sortBy = 'created_at',
        string $sortOrder = 'DESC'
    ): array {
        try {
            // Validate pagination parameters
            $page = max(1, $page);
            $perPage = min(100, max(1, $perPage));

            // Validate sort field
            $allowedSortFields = ['id', 'full_name', 'email', 'created_at', 'role', 'is_logged_in'];
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }

            // Validate sort order
            $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

            // Build WHERE clause
            $where = ['1=1'];
            $types = '';
            $params = [];

            // Add search condition
            if ($search && strlen($search) >= 2) {
                $where[] = '(full_name LIKE ? OR email LIKE ?)';
                $searchParam = '%' . $search . '%';
                $types .= 'ss';
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            // Add role filter
            if ($role && in_array($role, ['user', 'admin'])) {
                $where[] = 'role = ?';
                $types .= 's';
                $params[] = $role;
            }

            $whereClause = implode(' AND ', $where);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM users WHERE $whereClause";
            $countStmt = Database::prepare($countSql);
            if (!empty($params)) {
                $countStmt->bind_param($types, ...$params);
            }
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $total = $countResult->fetch_assoc()['total'];

            // Calculate pagination
            $offset = ($page - 1) * $perPage;
            $lastPage = (int) ceil($total / $perPage);

            // Get users
            $sql = "SELECT * FROM users WHERE $whereClause ORDER BY $sortBy $sortOrder LIMIT ? OFFSET ?";
            $stmt = Database::prepare($sql);
            $types .= 'ii';
            $params[] = $perPage;
            $params[] = $offset;

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = new self($row);
            }

            return [
                'users' => $users,
                'pagination' => [
                    'total' => (int) $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => $lastPage
                ]
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting all users', [
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            return [
                'users' => [],
                'pagination' => [
                    'total' => 0,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => 1
                ]
            ];
        }
    }
}