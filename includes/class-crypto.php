<?php
/**
 * Secure cryptographic functions for passwordless authentication
 * 
 * This class provides secure encryption/decryption functions
 */

defined('ABSPATH') or die('Direct access not allowed');

class My_Passwordless_Auth_Crypto {
    
    private const CIPHER = 'aes-256-cbc';
    private const KEY_LENGTH = 32; // 256 bits
    private const IV_LENGTH = 16;  // 128 bits for AES
    private const PBKDF2_ITERATIONS = 10000;
    
    /**
     * Encrypt data with a random IV for database storage
     * 
     * @param string $data The data to encrypt
     * @return string|false Base64 encoded encrypted data with IV, or false on failure
     */
    public static function encrypt_for_storage($data) {
        if (empty($data)) {
            return false;
        }

        try {
            // Generate a cryptographically secure random IV
            $iv = random_bytes(self::IV_LENGTH);
            
            // Derive key for database storage
            $key = self::derive_storage_key();

            // Encrypt the data
            $encrypted = openssl_encrypt(
                $data,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($encrypted === false) {
                return false;
            }

            // Combine IV and ciphertext
            $combined = base64_encode($iv . $encrypted);
            
            // Clear sensitive data from memory
            if (function_exists('sodium_memzero')) {
                sodium_memzero($key);
            }
            
            return $combined;
            
        } catch (Exception $e) {
            error_log('EasyAuth: Encryption failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrypt data from database storage
     * 
     * @param string $encrypted_data Base64 encoded encrypted data with IV
     * @return string|false Decrypted data or false on failure
     */
    public static function decrypt_from_storage($encrypted_data) {
        if (empty($encrypted_data)) {
            return false;
        }

        try {
            // Decode the combined string
            $decoded = base64_decode($encrypted_data);
            if ($decoded === false || strlen($decoded) < self::IV_LENGTH) {
                return false;
            }

            // Extract IV and ciphertext
            $iv = substr($decoded, 0, self::IV_LENGTH);
            $ciphertext = substr($decoded, self::IV_LENGTH);
            
            // Derive the same key
            $key = self::derive_storage_key();

            // Decrypt
            $decrypted = openssl_decrypt(
                $ciphertext,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            // Clear sensitive data
            if (function_exists('sodium_memzero')) {
                sodium_memzero($key);
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log('EasyAuth: Decryption failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Encrypt data for URL transmission (includes URL-safe encoding)
     * 
     * @param string $data The data to encrypt
     * @return string|false URL-safe encrypted data or false on failure
     */
    public static function encrypt_for_url($data) {
        if (empty($data)) {
            return false;
        }

        try {
            // Generate a cryptographically secure random IV
            $iv = random_bytes(self::IV_LENGTH);
            
            // Derive key for URL transmission
            $key = self::derive_url_key();

            // Encrypt the data
            $encrypted = openssl_encrypt(
                $data,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($encrypted === false) {
                return false;
            }

            // Combine IV and ciphertext, then make URL-safe
            $combined = $iv . $encrypted;
            $result = rtrim(strtr(base64_encode($combined), '+/', '-_'), '=');
            
            // Clear sensitive data from memory
            if (function_exists('sodium_memzero')) {
                sodium_memzero($key);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('EasyAuth: URL encryption failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Decrypt data from URL transmission
     * 
     * @param string $encrypted_data URL-safe encrypted data
     * @return string|false Decrypted data or false on failure
     */
    public static function decrypt_from_url($encrypted_data) {
        if (empty($encrypted_data)) {
            return false;
        }

        try {
            // Add padding if needed and convert from URL-safe base64
            $padded = $encrypted_data . str_repeat('=', (4 - strlen($encrypted_data) % 4) % 4);
            $decoded = base64_decode(strtr($padded, '-_', '+/'));
            
            if ($decoded === false || strlen($decoded) < self::IV_LENGTH) {
                return false;
            }

            // Extract IV and ciphertext
            $iv = substr($decoded, 0, self::IV_LENGTH);
            $ciphertext = substr($decoded, self::IV_LENGTH);
            
            // Derive the same key
            $key = self::derive_url_key();

            // Decrypt
            $decrypted = openssl_decrypt(
                $ciphertext,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            // Clear sensitive data
            if (function_exists('sodium_memzero')) {
                sodium_memzero($key);
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log('EasyAuth: URL decryption failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Derive a cryptographic key for database storage using WordPress salts
     * 
     * @return string Binary key material
     */
    private static function derive_storage_key() {
        // Combine multiple WordPress salts for entropy
        $salt_material = wp_salt('auth') . wp_salt('secure_auth') . wp_salt('logged_in');
        
        // Use proper key derivation with password stretching
        return hash_pbkdf2(
            'sha256',
            'my_passwordless_auth_storage',
            $salt_material,
            self::PBKDF2_ITERATIONS,
            self::KEY_LENGTH,
            true
        );
    }

    /**
     * Derive a cryptographic key for URL transmission using WordPress salts
     * 
     * @return string Binary key material
     */
    private static function derive_url_key() {
        // Combine different WordPress salts for URL key
        $salt_material = wp_salt('nonce') . wp_salt('secure_auth') . wp_salt('auth');
        
        // Use proper key derivation with password stretching
        return hash_pbkdf2(
            'sha256',
            'my_passwordless_auth_url',
            $salt_material,
            self::PBKDF2_ITERATIONS,
            self::KEY_LENGTH,
            true
        );
    }

    /**
     * Encrypt user ID for magic links (backward compatibility wrapper)
     * 
     * @param int $user_id The user ID to encrypt
     * @return string|false Encrypted user ID or false on failure
     */
    public static function encrypt_user_id($user_id) {
        return self::encrypt_for_url((string)$user_id);
    }

    /**
     * Decrypt user ID from magic links (backward compatibility wrapper)
     * 
     * @param string $encrypted_id The encrypted user ID
     * @return int|false Decrypted user ID or false on failure
     */
    public static function decrypt_user_id($encrypted_id) {
        $decrypted = self::decrypt_from_url($encrypted_id);
        
        if ($decrypted === false || !is_numeric($decrypted)) {
            return false;
        }
        
        return (int)$decrypted;
    }

    /**
     * Check if the system supports secure cryptographic functions
     * 
     * @return bool True if system is properly configured for crypto
     */
    public static function is_system_secure() {
        // Check for required PHP extensions
        if (!extension_loaded('openssl')) {
            return false;
        }
        
        // Check for secure random number generation
        if (!function_exists('random_bytes')) {
            return false;
        }
        
        // Check WordPress salts are defined
        if (!wp_salt('auth') || !wp_salt('secure_auth') || !wp_salt('logged_in') || !wp_salt('nonce')) {
            return false;
        }
        
        return true;
    }
}
