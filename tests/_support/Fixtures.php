<?php

namespace Tests\Support;

use CodeIgniter\Database\BaseConnection;

/**
 * A small marketplace, made for the corners. Every relation has rows that match, rows that do not,
 * a value that is null, a foreign key that is null, one that points nowhere, a soft deleted row
 * on either side, and the many-to-many pivots have duplicates.
 *
 * Bo (user 2) is the caller in the tests with rules: approved in Alpha (1) and Beta (2).
 */
final class Fixtures
{
    public static function load(BaseConnection $db): void
    {
        $tables = ['products_target_workspaces', 'favorites', 'order_lines', 'products', 'orders', 'users_workspaces', 'users', 'workspaces', 'addresses', '`groups`', 'deletions'];
        foreach ($tables as $table) {
            $db->query("DROP TABLE IF EXISTS {$table}");
        }
        $db->query('CREATE TABLE deletions (id INT AUTO_INCREMENT PRIMARY KEY, created DATETIME NULL)');
        $db->query('CREATE TABLE `groups` (id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT NULL, name VARCHAR(63) NULL, KEY parent_id (parent_id))');
        $db->query('CREATE TABLE addresses (id INT AUTO_INCREMENT PRIMARY KEY, city VARCHAR(63) NULL, country_code CHAR(2) NULL)');
        $db->query('CREATE TABLE workspaces (id INT AUTO_INCREMENT PRIMARY KEY, group_id INT NULL, address_id INT NULL, invoice_address_id INT NULL, name VARCHAR(63) NULL, deletion_id INT NULL)');
        $db->query('CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NULL, age INT NULL, deletion_id INT NULL)');
        $db->query('CREATE TABLE users_workspaces (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, workspace_id INT NULL, invited_by_id INT NULL, status VARCHAR(31) NOT NULL, deletion_id INT NULL, KEY user_id (user_id), KEY workspace_id (workspace_id))');
        $db->query('CREATE TABLE orders (id INT AUTO_INCREMENT PRIMARY KEY, parent_id INT NULL, buyer_workspace_id INT NULL, seller_workspace_id INT NULL, shipping_address_id INT NULL, invoice_address_id INT NULL, reference VARCHAR(63) NOT NULL, total INT NULL, deletion_id INT NULL)');
        $db->query('CREATE TABLE products (id INT AUTO_INCREMENT PRIMARY KEY, product_no VARCHAR(31) NOT NULL, workspace_id INT NULL, name VARCHAR(63) NULL, is_active TINYINT(1) NOT NULL DEFAULT 0, deletion_id INT NULL, UNIQUE KEY product_no (product_no))');
        $db->query('CREATE TABLE order_lines (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NULL, product_no VARCHAR(31) NULL, quantity INT NULL)');
        $db->query('CREATE TABLE favorites (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, product_no VARCHAR(31) NULL)');
        // As products_active_campaign_origins: no unique key, duplicates happen
        $db->query('CREATE TABLE products_target_workspaces (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NULL, workspace_id INT NULL)');
        $db->query('INSERT INTO deletions (id, created) VALUES (1, NOW())');

        $db->query("INSERT INTO `groups` (id, parent_id, name) VALUES (1, NULL, 'Nordic'), (2, 1, 'Denmark'), (3, 1, 'Sweden'), (4, 2, 'Jutland'), (5, 99, 'Lost'), (6, 4, NULL)");
        $db->query("INSERT INTO addresses (id, city, country_code) VALUES (1, 'Aarhus', 'DK'), (2, 'Malmo', 'SE'), (3, 'Hidden', 'DK'), (4, 'Oslo', NULL), (5, 'Hidden', 'NO'), (6, NULL, 'DK')");
        $db->query("INSERT INTO workspaces (id, group_id, address_id, invoice_address_id, name, deletion_id) VALUES
            (1, 4, 1, 2, 'Alpha', NULL),
            (2, 3, 2, NULL, 'Beta', NULL),
            (3, 2, 3, 1, 'Gamma', NULL),
            (4, 1, 4, 5, NULL, NULL),
            (5, 2, 1, NULL, 'Deleted', 1),
            (6, 99, 99, 99, 'Orphan', NULL)");
        $db->query("INSERT INTO users (id, email, age, deletion_id) VALUES
            (1, 'ann@a.dk', 34, NULL), (2, 'bo@b.dk', 25, NULL), (3, NULL, NULL, NULL), (4, 'cy@a.dk', 30, NULL), (5, 'eve@a.dk', 30, 1), (6, 'dan@c.dk', 41, NULL)");
        $db->query("INSERT INTO users_workspaces (user_id, workspace_id, invited_by_id, status, deletion_id) VALUES
            (1, 1, NULL, 'approved', NULL),
            (1, 3, 2, 'pending', NULL),
            (2, 2, 1, 'approved', NULL),
            (2, 1, 1, 'approved', NULL),
            (2, 2, NULL, 'approved', NULL),
            (3, 1, 4, 'approved', NULL),
            (4, 4, NULL, 'approved', NULL),
            (5, 1, NULL, 'approved', NULL),
            (4, 5, 1, 'approved', NULL),
            (1, 99, NULL, 'approved', NULL),
            (6, 2, 2, 'approved', 1),
            (2, 3, NULL, 'pending', NULL)");
        $db->query("INSERT INTO orders (id, parent_id, buyer_workspace_id, seller_workspace_id, shipping_address_id, invoice_address_id, reference, total, deletion_id) VALUES
            (1, NULL, 1, 2, 1, 2, 'A-1', 100, NULL),
            (2, 1, 2, 1, 2, 2, 'B-2', NULL, NULL),
            (3, 1, 3, 4, NULL, 3, 'G-3', 50, NULL),
            (4, NULL, 1, 1, 4, NULL, 'A-4', 75, NULL),
            (5, 2, 5, 2, 1, 1, 'D-5', 10, NULL),
            (6, NULL, NULL, 99, 99, NULL, 'X-6', 0, NULL),
            (7, NULL, 1, 2, 1, 1, 'A-7', 20, 1),
            (8, 99, 4, 3, 5, 6, 'N-8', 300, NULL),
            (9, 4, 3, 2, 3, 5, 'G-9', 60, NULL)");
        $db->query("INSERT INTO products (id, product_no, workspace_id, name, is_active, deletion_id) VALUES
            (1, 'P-100', 2, 'Apple', 1, NULL), (2, 'P-200', 1, 'Banana', 0, NULL), (3, 'P-300', 3, 'Cherry', 0, NULL),
            (4, 'P-400', 4, NULL, 1, NULL), (5, 'P-500', 2, 'Gone', 1, 1)");
        $db->query("INSERT INTO order_lines (order_id, product_no, quantity) VALUES
            (1, 'P-100', 2), (1, 'P-200', 1), (2, 'P-100', 5), (3, 'P-300', 1), (4, 'P-999', 1), (7, 'P-100', 1), (99, 'P-200', 3), (9, 'P-300', 2), (9, NULL, NULL), (5, 'P-500', 1)");
        $db->query("INSERT INTO favorites (user_id, product_no) VALUES (1, 'P-100'), (1, 'P-200'), (2, 'P-100'), (3, 'P-999'), (5, 'P-300'), (2, 'P-300'), (NULL, 'P-400')");
        $db->query('INSERT INTO products_target_workspaces (product_id, workspace_id) VALUES (1, 1), (1, 1), (1, 2), (2, 3), (3, 99), (4, 5), (5, 1), (2, NULL)');
    }
}
