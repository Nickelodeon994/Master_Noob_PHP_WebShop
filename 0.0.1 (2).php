<?php

/**
 * 超轻量级网页市场
 * 
 * @version 0.0.1
 * @build 2026-02-12
 * @author Nickelodeon994
 * @link https://github.com/Nickelodeon994/Master_Noob_PHP_WebShop
 * @license Apache-2.0
 * 
 * 更新日志：
 * - 0.0.1 (2026-02-12) 初始版本
 *   * 加入了对Minecraft:lang文件支持
 */


/**
 * Copyright 2026 Nickelodeon994
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


session_start();

define('DB_FILE', 'shop_data.json');

define('SHOP_DATA_DIR', __DIR__ . '/shop_data');
define('SHOP_USERS_DIR', SHOP_DATA_DIR . '/Users');
define('SHOP_MEDIA_DIR', SHOP_DATA_DIR . '/Media');

function get_shop_user($gamename) {
    $user_file = SHOP_USERS_DIR . '/' . $gamename . '.json';
    if (!file_exists($user_file)) {
        return null;
    }
    $content = file_get_contents($user_file);
    $data = json_decode($content, true);
    return is_array($data) ? $data : null;
}

function get_user_balance($gamename) {
    $user = get_shop_user($gamename);
    if (!$user) return 0.0;
    return isset($user['balance']) ? floatval($user['balance']) : 0.0;
}

function set_user_balance($gamename, $amount) {
    $amount = round(floatval($amount), 2);
    return save_shop_user($gamename, ['balance' => $amount]);
}

function change_user_balance($gamename, $delta) {
    $current = get_user_balance($gamename);
    $new = $current + floatval($delta);
    if ($new < 0) return false;
    return set_user_balance($gamename, $new);
}

function is_anchor_item($name) {
    if (!$name) return false;
    $settings = get_global_settings();
    $anchor_item = $settings['anchor_item'] ?? 'minecraft:iron_ingot';
    $short_anchor = preg_replace('/^[^:]+:/', '', $anchor_item);
    $short_name = preg_replace('/^[^:]+:/', '', $name);
    return $short_name === $short_anchor;
}

function is_iron_ingot_name($name) {
    return is_anchor_item($name);
}

function save_shop_user($gamename, $fields) {
    $user = get_shop_user($gamename);
    if (!$user) {
        return false;
    }
    foreach ($fields as $key => $value) {
        $user[$key] = $value;
    }
    $user_file = SHOP_USERS_DIR . '/' . $gamename . '.json';
    file_put_contents($user_file, json_encode($user, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return true;
}

function verify_password($input_password, $hashed_password) {
    return password_verify($input_password, $hashed_password);
}

function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function get_user_gamename($gamename) {
    $user = get_shop_user($gamename);
    return $user['gamename'] ?? ($user['gamename'] ?? '未知用户');
}

function is_shop_admin($gamename) {
    $user = get_shop_user($gamename);
    return ($user['role'] ?? '') === 'admin';
}

function has_user_shop($gamename) {
    $user = get_shop_user($gamename);
    return $user['has_shop'] ?? false;
}

function set_user_shop($gamename, $has_shop) {
    return save_shop_user($gamename, ['has_shop' => $has_shop]);
}


function ensure_user_media_dirs($gamename) {
    $base = SHOP_MEDIA_DIR . '/' . $gamename;
    $dirs = ['Inventory', 'Bill', 'Ps', 'Feedback', 'Good', 'Cart', 'Icon'];
    foreach ($dirs as $dir) {
        $path = $base . '/' . $dir;
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

function upload_image_for_user($gamename, $file_input_name, $subdir = 'Icon') {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    ensure_user_media_dirs($gamename);

    $upload_dir = SHOP_MEDIA_DIR . '/' . $gamename . '/' . $subdir . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }

    $file_name = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES[$file_input_name]['name']));
    $file_path = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $file_path)) {
        return $gamename . '/' . $subdir . '/' . $file_name;
    }

    return null;
}

function get_user_avatar_url($gamename) {
    $icon_dir = SHOP_MEDIA_DIR . '/' . $gamename . '/Icon/';
    if (!is_dir($icon_dir)) {
        return null;
    }
    $files = glob($icon_dir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    if (empty($files)) {
        return null;
    }
    $first = $files[0];
    return str_replace(SHOP_MEDIA_DIR . '/', '', $first);
}

function update_user_avatar($gamename, $avatar_url) {
    $user = get_shop_user($gamename);
    if ($user) {
        $user['avatar'] = $avatar_url;
        save_shop_user($gamename, $user);
    }
}

function get_user_inventory($gamename) {
    $dir = SHOP_MEDIA_DIR . '/' . $gamename . '/Inventory';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.json');
    $inventory = [];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $item = json_decode($content, true);
        if (is_array($item)) {
            $item_id = basename($file, '.json');
            $inventory[$item_id] = $item;
        }
    }

    $groups = [];
    foreach ($inventory as $item_id => $item) {
        $name = $item['name'] ?? '';
        $nbt = $item['nbt'] ?? '';
        $key = $name . '|' . $nbt;
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'total_quantity' => 0,
                'first_item_id' => $item_id,
                'item_ids' => [],
                'item_data' => $item
            ];
        }
        $groups[$key]['total_quantity'] += intval($item['quantity'] ?? 1);
        $groups[$key]['item_ids'][] = $item_id;
    }

    foreach ($groups as $key => $group) {
        if (count($group['item_ids']) > 1) {
            $first_id = $group['first_item_id'];
            $item_data = $group['item_data'];
            $item_data['quantity'] = $group['total_quantity'];
            save_inventory_item($gamename, $first_id, $item_data);

            foreach ($group['item_ids'] as $id) {
                if ($id !== $first_id) {
                    delete_inventory_item($gamename, $id);
                }
            }
        }
    }

    $files = glob($dir . '/*.json');
    $merged = [];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $item = json_decode($content, true);
        if (is_array($item)) {
            $item_id = basename($file, '.json');
            $merged[$item_id] = $item;
        }
    }
    return $merged;
}

function save_inventory_item($gamename, $item_id, $item) {
    $dir = SHOP_MEDIA_DIR . '/' . $gamename . '/Inventory';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . $item_id . '.json';
    file_put_contents($file, json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function delete_inventory_item($gamename, $item_id) {
    $file = SHOP_MEDIA_DIR . '/' . $gamename . '/Inventory/' . $item_id . '.json';
    if (file_exists($file)) {
        unlink($file);
        return true;
    }
    return false;
}

function get_user_transactions($gamename) {
    $dir = SHOP_MEDIA_DIR . '/' . $gamename . '/Bill';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.json');
    $transactions = [];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $transaction = json_decode($content, true);
        if (is_array($transaction)) {
            $transaction_id = basename($file, '.json');
            $transactions[$transaction_id] = $transaction;
        }
    }
    return $transactions;
}

function save_transaction($gamename, $transaction_id, $transaction) {
    $dir = SHOP_MEDIA_DIR . '/' . $gamename . '/Bill';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . $transaction_id . '.json';
    file_put_contents($file, json_encode($transaction, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function get_user_products($gamename) {
    $dir = SHOP_MEDIA_DIR . '/' . $gamename . '/Good';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.json');
    $products = [];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $product = json_decode($content, true);
        if (is_array($product)) {
            $product_id = basename($file, '.json');
            $products[$product_id] = $product;
        }
    }
    return $products;
}

function save_product($gamename, $product_id, $product) {
    $dir = SHOP_MEDIA_DIR . '/' . $gamename . '/Good';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . $product_id . '.json';
    file_put_contents($file, json_encode($product, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function delete_product_file($gamename, $product_id) {
    $file = SHOP_MEDIA_DIR . '/' . $gamename . '/Good/' . $product_id . '.json';
    if (file_exists($file)) {
        unlink($file);
        return true;
    }
    return false;
}

function get_user_feedbacks($gamename) {
    $dir = SHOP_MEDIA_DIR . '/' . $gamename . '/Feedback';
    if (!is_dir($dir)) {
        return [];
    }
    $files = glob($dir . '/*.json');
    $feedbacks = [];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $feedback = json_decode($content, true);
        if (is_array($feedback)) {
            $feedback_id = basename($file, '.json');
            $feedbacks[$feedback_id] = $feedback;
        }
    }
    return $feedbacks;
}

function save_feedback($gamename, $feedback_id, $feedback) {
    $dir = SHOP_MEDIA_DIR . '/' . $gamename . '/Feedback';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . $feedback_id . '.json';
    file_put_contents($file, json_encode($feedback, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function get_all_shops() {
    $shops = [];
    $user_dirs = glob(SHOP_MEDIA_DIR . '/*', GLOB_ONLYDIR);
    foreach ($user_dirs as $user_dir) {
        $shop_file = $user_dir . '/shop.json';
        if (file_exists($shop_file)) {
            $content = file_get_contents($shop_file);
            $shop = json_decode($content, true);
            if (is_array($shop)) {
                $shop_id = basename($user_dir);
                $shops[$shop_id] = $shop;
            }
        }
    }
    return $shops;
}

function save_shop($gamename, $shop_data) {
    $dir = SHOP_MEDIA_DIR . '/' . $gamename;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/shop.json';
    file_put_contents($file, json_encode($shop_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function delete_shop($gamename) {
    $file = SHOP_MEDIA_DIR . '/' . $gamename . '/shop.json';
    if (file_exists($file)) {
        unlink($file);
        return true;
    }
    return false;
}

function get_user_cart($gamename) {
    $dir = SHOP_MEDIA_DIR . '/' . $gamename . '/Cart';
    $file = $dir . '/cart.json';
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    $cart = json_decode($content, true);
    return is_array($cart) ? $cart : [];
}

function save_user_cart($gamename, $cart) {
    $dir = SHOP_MEDIA_DIR . '/' . $gamename . '/Cart';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = $dir . '/cart.json';
    file_put_contents($file, json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function get_global_settings() {
    $settings_file = SHOP_DATA_DIR . '/shop_settings.json';
    $default = [
        'transaction_fee' => 0.05,
        'shop_opening_fee' => 100,
        'shop_enabled' => false,
        'lang_file' => '',
        'allow_web_registration' => true,
        'anchor_item' => 'minecraft:iron_ingot',
        'exchange_rate' => 1.0,
        'api_token' => '1145141919810'
    ];
    if (file_exists($settings_file)) {
        $content = file_get_contents($settings_file);
        $data = json_decode($content, true);
        if (is_array($data)) {
            return array_merge($default, $data);
        }
    }
    return $default;
}

function save_global_settings($settings) {
    $settings_file = SHOP_DATA_DIR . '/shop_settings.json';
    $default = [
        'transaction_fee' => 0.05,
        'shop_opening_fee' => 100,
        'shop_enabled' => false,
        'lang_file' => '',
        'allow_web_registration' => true,
        'anchor_item' => 'minecraft:iron_ingot',
        'exchange_rate' => 1.0,
        'api_token' => '1145141919810'
    ];
    $to_save = array_intersect_key($settings, $default);
    $final = array_merge($default, $to_save);
    file_put_contents($settings_file, json_encode($final, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function get_available_lang_files() {
    $lang_dir = __DIR__ . '/lang';
    if (!is_dir($lang_dir)) {
        return [];
    }
    $files = glob($lang_dir . '/*.lang');
    $result = [];
    foreach ($files as $file) {
        $basename = basename($file);
        $result[$basename] = $basename;
    }
    return $result;
}

function load_lang_mappings($lang_file) {
    static $cache = [];
    if (isset($cache[$lang_file])) {
        return $cache[$lang_file];
    }

    $lang_path = __DIR__ . '/lang/' . $lang_file;
    if (!file_exists($lang_path)) {
        $cache[$lang_file] = [];
        return [];
    }

    $content = file_get_contents($lang_path);
    $lines = explode("\n", $content);
    $mappings = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $mappings[$key] = $value;
    }

    $cache[$lang_file] = $mappings;
    return $mappings;
}

function get_item_chinese_name_from_lang($item_name, $lang_file) {
    if (empty($lang_file)) {
        return $item_name;
    }

    $mappings = load_lang_mappings($lang_file);
    if (empty($mappings)) {
        return $item_name;
    }

    $short_name = preg_replace('/^[^:]+:/', '', $item_name);
    $key = 'item.' . $short_name . '.name';
    if (isset($mappings[$key])) {
        return $mappings[$key];
    }

    $key = 'tile.' . $short_name . '.name';
    if (isset($mappings[$key])) {
        return $mappings[$key];
    }

    return $item_name;
}

function get_enchant_chinese_name_from_lang($enchant, $lang_file) {
    if (empty($lang_file)) {
        return $enchant;
    }

    $mappings = load_lang_mappings($lang_file);
    if (empty($mappings)) {
        return $enchant;
    }

    $key = 'enchantment.' . strtolower($enchant);
    if (isset($mappings[$key])) {
        return $mappings[$key];
    }

    $keys = [
        'enchantment.' . $enchant,
        'enchantment.' . strtolower($enchant),
        'enchantment.' . str_replace('_', '', $enchant),
    ];
    foreach ($keys as $k) {
        if (isset($mappings[$k])) {
            return $mappings[$k];
        }
    }

    return $enchant;
}

function get_global_categories() {
    return [
        'all' => '全部商品',
        'tools' => '工具',
        'armor' => '装备',
        'materials' => '材料',
        'food' => '食物',
        'other' => '其他'
    ];
}

function get_all_products() {
    $products = [];
    $user_dirs = glob(SHOP_MEDIA_DIR . '/*', GLOB_ONLYDIR);
    foreach ($user_dirs as $user_dir) {
        $gamename = basename($user_dir);
        $good_dir = $user_dir . '/Good';
        if (is_dir($good_dir)) {
            $files = glob($good_dir . '/*.json');
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $product = json_decode($content, true);
                if (is_array($product)) {
                    $product_id = basename($file, '.json');
                    $product['seller_id'] = $gamename;
                    $product['seller_gamename'] = $gamename;
                    $product['shop_id'] = $gamename;

                    if (!empty($product['image']) && strpos($product['image'], '?action=media') === false) {
                        $prefix = 'shop_data/Media/';
                        $image_path = $product['image'];
                        if (strpos($image_path, $prefix) === 0) {
                            $image_path = substr($image_path, strlen($prefix));
                        }
                        $product['image'] = '?action=media&file=' . urlencode($image_path);
                    }

                    $products[$product_id] = $product;
                }
            }
        }
    }
    return $products;
}

function get_all_transactions() {
    $transactions = [];
    $user_dirs = glob(SHOP_MEDIA_DIR . '/*', GLOB_ONLYDIR);
    foreach ($user_dirs as $user_dir) {
        $gamename = basename($user_dir);
        $bill_dir = $user_dir . '/Bill';
        if (is_dir($bill_dir)) {
            $files = glob($bill_dir . '/*.json');
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $transaction = json_decode($content, true);
                if (is_array($transaction)) {
                    $transaction_id = basename($file, '.json');
                    $transactions[$transaction_id] = $transaction;
                }
            }
        }
    }
    return $transactions;
}


function init_db() {
}

function read_db() {
    return [
        'users' => [],
        'products' => [],
        'shops' => [],
        'transactions' => [],
        'settings' => get_global_settings(),
        'api_requests' => [],
        'categories' => get_global_categories(),
        'cart' => [],
        'reviews' => [],
        'shop_reviews' => []
    ];
}

function write_db($data) {
}

function get_item_chinese_name($item_name) {
    $settings = get_global_settings();
    $lang_file = $settings['lang_file'] ?? '';
    return get_item_chinese_name_from_lang($item_name, $lang_file);
}



function parse_nbt_to_attributes($nbt_string, $item_name) {
    if (empty($nbt_string) || $nbt_string === '{}') {
        return '';
    }

    $nbt = json_decode($nbt_string, true);
    if (!$nbt) {
        return '';
    }

    $attributes = [];

    if (isset($nbt['enchants'])) {
        $enchants = [];
        foreach ($nbt['enchants'] as $enchant => $level) {
            $enchant_name = get_enchant_chinese_name($enchant);
            $enchants[] = $enchant_name . ' ' . $level;
        }
        if (!empty($enchants)) {
            $attributes[] = '附魔: ' . implode(', ', $enchants);
        }
    }

    if (isset($nbt['container_items'])) {
        $items = [];
        foreach ($nbt['container_items'] as $item) {
            $item_name_chinese = get_item_chinese_name($item['name']);
            $items[] = $item_name_chinese . ' x' . $item['quantity'];
        }
        if (!empty($items)) {
            $attributes[] = '包含: ' . implode(', ', $items);
        }
    }

    if (isset($nbt['display'])) {
        if (isset($nbt['display']['Name'])) {
            $attributes[] = '名称: ' . $nbt['display']['Name'];
        }
        if (isset($nbt['display']['Lore'])) {
            $attributes[] = '描述: ' . implode(', ', $nbt['display']['Lore']);
        }
    }

    if (isset($nbt['CustomModelData'])) {
        $attributes[] = '自定义模型: ' . $nbt['CustomModelData'];
    }

    if (isset($nbt['Unbreakable']) && $nbt['Unbreakable'] === 1) {
        $attributes[] = '不可破坏';
    }

    if (isset($nbt['AttributeModifiers'])) {
        $modifiers = [];
        foreach ($nbt['AttributeModifiers'] as $modifier) {
            $attr_name = get_attribute_chinese_name($modifier['AttributeName'] ?? '');
            $amount = $modifier['Amount'] ?? 0;
            $operation = $modifier['Operation'] ?? 0;

            $op_str = '';
            switch ($operation) {
                case 0: $op_str = '+'; break;
                case 1: $op_str = '×'; break;
                case 2: $op_str = '×'; break;
            }

            $modifiers[] = $attr_name . ' ' . $op_str . $amount;
        }
        if (!empty($modifiers)) {
            $attributes[] = '属性: ' . implode(', ', $modifiers);
        }
    }

    if (isset($nbt['Potion'])) {
        $potion_name = get_potion_chinese_name($nbt['Potion']);
        $attributes[] = '药水: ' . $potion_name;
    }

    if (isset($nbt['StoredEnchantments'])) {
        $enchants = [];
        foreach ($nbt['StoredEnchantments'] as $enchant) {
            $enchant_name = get_enchant_chinese_name($enchant['id'] ?? '');
            $level = $enchant['lvl'] ?? 1;
            $enchants[] = $enchant_name . ' ' . $level;
        }
        if (!empty($enchants)) {
            $attributes[] = '附魔: ' . implode(', ', $enchants);
        }
    }

    if (isset($nbt['Enchantments'])) {
        $enchants = [];
        foreach ($nbt['Enchantments'] as $enchant) {
            $enchant_name = get_enchant_chinese_name($enchant['id'] ?? '');
            $level = $enchant['lvl'] ?? 1;
            $enchants[] = $enchant_name . ' ' . $level;
        }
        if (!empty($enchants)) {
            $attributes[] = '附魔: ' . implode(', ', $enchants);
        }
    }

    if (isset($nbt['RepairCost'])) {
        $attributes[] = '修复成本: ' . $nbt['RepairCost'];
    }

    if (isset($nbt['Damage'])) {
        $attributes[] = '耐久度: ' . $nbt['Damage'];
    }

    if (isset($nbt['Enchanted']) && $nbt['Enchanted'] === 1) {
        $attributes[] = '已附魔';
    }

    if (isset($nbt['CustomPotionEffects'])) {
        $effects = [];
        foreach ($nbt['CustomPotionEffects'] as $effect) {
            $effect_name = get_effect_chinese_name($effect['Id'] ?? '');
            $duration = $effect['Duration'] ?? 0;
            $amplifier = $effect['Amplifier'] ?? 0;

            $effects[] = $effect_name . ' ' . ($amplifier + 1) . '级 ' . ($duration / 20) . '秒';
        }
        if (!empty($effects)) {
            $attributes[] = '效果: ' . implode(', ', $effects);
        }
    }

    if (isset($nbt['BlockEntityTag'])) {
        $block_tag = $nbt['BlockEntityTag'];
        if (isset($block_tag['Items'])) {
            $items = [];
            foreach ($block_tag['Items'] as $item) {
                $item_name_chinese = get_item_chinese_name($item['id'] ?? '');
                $count = $item['Count'] ?? 1;
                $items[] = $item_name_chinese . ' x' . $count;
            }
            if (!empty($items)) {
                $attributes[] = '包含: ' . implode(', ', $items);
            }
        }
    }

    return implode(' | ', $attributes);
}

function get_enchant_chinese_name($enchant) {
    $settings = get_global_settings();
    $lang_file = $settings['lang_file'] ?? '';
    if (empty($lang_file)) {
        return $enchant;
    }
    return get_enchant_chinese_name_from_lang($enchant, $lang_file);
}

function get_attribute_chinese_name($attribute) {
    $settings = get_global_settings();
    $lang_file = $settings['lang_file'] ?? '';
    if (empty($lang_file)) {
        return $attribute;
    }
    $mappings = load_lang_mappings($lang_file);
    if (empty($mappings)) {
        return $attribute;
    }
    $key = 'attribute.name.' . str_replace('_', '', $attribute);
    if (isset($mappings[$key])) {
        return $mappings[$key];
    }
    if (isset($mappings[$attribute])) {
        return $mappings[$attribute];
    }
    return $attribute;
}

function get_potion_chinese_name($potion) {
    $settings = get_global_settings();
    $lang_file = $settings['lang_file'] ?? '';
    if (empty($lang_file)) {
        return $potion;
    }
    $mappings = load_lang_mappings($lang_file);
    if (empty($mappings)) {
        return $potion;
    }
    $short = preg_replace('/^[^:]+:/', '', $potion);
    $key = 'potion.' . $short;
    if (isset($mappings[$key])) {
        return $mappings[$key];
    }
    $key2 = $key . '.name';
    if (isset($mappings[$key2])) {
        return $mappings[$key2];
    }
    return $potion;
}

function get_effect_chinese_name($effect_id) {
    $settings = get_global_settings();
    $lang_file = $settings['lang_file'] ?? '';
    if (empty($lang_file)) {
        return is_numeric($effect_id) ? '未知效果' : $effect_id;
    }
    $mappings = load_lang_mappings($lang_file);
    if (empty($mappings)) {
        return is_numeric($effect_id) ? '未知效果' : $effect_id;
    }
    $key = 'effect.' . $effect_id;
    if (isset($mappings[$key])) {
        return $mappings[$key];
    }
    static $fallback = [
        1 => '速度',
    ];
    if (is_numeric($effect_id) && isset($fallback[$effect_id])) {
        return $fallback[$effect_id];
    }
    return is_numeric($effect_id) ? '未知效果' : $effect_id;
}


function get_logged_in_user() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $gamename = $_SESSION['user_id'];
    $shop_user = get_shop_user($gamename);
    if (!$shop_user) {
        return null;
    }
    $avatar_url = null;
    if (!empty($shop_user['use_remote_media']) && !empty($shop_user['avatar_remote'])) {
        $avatar_url = $shop_user['avatar_remote'];
    } elseif (!empty($shop_user['avatar'])) {
        $avatar = $shop_user['avatar'];
        if (strpos($avatar, '?action=media') === 0) {
            $avatar_url = $avatar;
        } else {
            $avatar_url = '?action=media&file=' . urlencode($avatar);
        }
    }
    $user = [
        'gamename' => $gamename,
        'username' => $shop_user['gamename'] ?? '',
        'password' => $shop_user['password'] ?? '',
        'avatar' => $avatar_url,
        'has_shop' => $shop_user['has_shop'] ?? false,
        'is_admin' => ($shop_user['role'] ?? '') === 'admin',
        'inventory' => get_user_inventory($gamename),
        'balance' => get_user_balance($gamename),
        'created_at' => $shop_user['created_at'] ?? date('Y-m-d H:i:s')
    ];
    return $user;
}


function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ?action=login');
        exit;
    }
}

function check_shop_permission() {
    $user = get_logged_in_user();
    if (!$user || !$user['has_shop']) {
        header('Location: ?action=personal_center');
        exit;
    }
}

function check_admin_permission() {
    $user = get_logged_in_user();
    if (!$user || !$user['is_admin']) {
        header('Location: ?action=personal_center');
        exit;
    }
}

function page_header($title) {
    $user = get_logged_in_user();
    $settings = get_global_settings();
    $shop_enabled = $settings['shop_enabled'];
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #fff;
            min-height: 100vh;
            color: #000;
            line-height: 1.5;
            font-size: 15px;
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 40px; }

        .navbar {
            background: #fff;
            color: #000;
            padding: 24px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #000;
            margin-bottom: 60px;
        }
        .navbar a {
            color: #000;
            text-decoration: none;
            margin: 0 0 0 32px;
            padding: 0;
            font-weight: 400;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: opacity 0.2s;
        }
        .navbar a:hover {
            opacity: 0.5;
        }
        .navbar .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 14px;
        }
        .navbar .avatar {
            width: 36px;
            height: 36px;
            border-radius: 0;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 500;
            overflow: hidden;
        }

        .content {
            background: #fff;
            padding: 0;
            margin-top: 0;
            animation: fadeIn 0.5s ease;
        }

        h1 {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 40px;
            letter-spacing: -0.02em;
            line-height: 1.1;
        }

        h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 24px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .search-box {
            margin-bottom: 60px;
            display: flex;
            gap: 0;
            background: #fff;
            padding: 0;
            border-bottom: 1px solid #000;
        }
        .search-box input {
            flex: 1;
            padding: 16px 0;
            border: none;
            border-bottom: none;
            font-size: 18px;
            background: transparent;
            color: #000;
            font-weight: 400;
        }
        .search-box input:focus {
            outline: none;
        }
        .search-box input::placeholder {
            color: #999;
        }
        .search-box button {
            padding: 16px 32px;
            background: #000;
            color: #fff;
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            transition: background 0.2s;
        }
        .search-box button:hover {
            background: #333;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 40px;
        }
        .product-card {
            background: #fff;
            border: 1px solid #e5e5e5;
            padding: 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .product-card:hover {
            border-color: #000;
        }
        .product-card .image-container {
            width: 100%;
            height: 240px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            overflow: hidden;
            margin-bottom: 0;
            border-bottom: 1px solid #e5e5e5;
        }
        .product-card .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: opacity 0.3s ease;
        }
        .product-card:hover .image-container img {
            opacity: 0.9;
        }
        .product-card h3 {
            margin: 24px 24px 8px;
            font-size: 16px;
            color: #000;
            font-weight: 600;
            line-height: 1.4;
        }
        .product-card .price {
            color: #000;
            font-weight: 600;
            font-size: 18px;
            margin: 8px 24px;
        }
        .product-card .shop-name {
            color: #666;
            font-size: 13px;
            margin: 8px 24px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .product-card .stock {
            color: #666;
            font-size: 13px;
            margin: 8px 24px 24px;
        }

        .form-group { margin-bottom: 32px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #000;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 16px 0;
            border: none;
            border-bottom: 1px solid #000;
            font-size: 16px;
            transition: border-color 0.2s;
            background: transparent;
            color: #000;
            border-radius: 0;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #666;
            outline: none;
        }
        .form-group input::placeholder, .form-group textarea::placeholder {
            color: #999;
        }
        .btn {
            padding: 16px 32px;
            background: #000;
            color: #fff;
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn:hover {
            background: #333;
        }
        .btn-danger {
            background: #fff;
            color: #000;
            border: 1px solid #000;
        }
        .btn-danger:hover {
            background: #000;
            color: #fff;
        }
        .btn-success {
            background: #000;
            color: #fff;
        }
        .btn-success:hover {
            background: #333;
        }
        .btn-secondary {
            background: #fff;
            color: #000;
            border: 1px solid #000;
        }
        .btn-secondary:hover {
            background: #000;
            color: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
            background: #fff;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        th, td {
            padding: 20px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }
        th {
            background: #fff;
            color: #000;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 12px;
            border-bottom: 2px solid #000;
        }
        tr:hover {
            background: #f5f5f5;
        }

        .alert {
            padding: 20px 24px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
            border-left: 3px solid;
        }
        .alert-success {
            background: #f0f0f0;
            color: #000;
            border-color: #000;
        }
        .alert-error {
            background: #f0f0f0;
            color: #000;
            border-color: #666;
        }

        .profile-section {
            margin-bottom: 48px;
            background: #fff;
            padding: 32px 0;
            border-bottom: 1px solid #e5e5e5;
        }
        .profile-section h2 {
            border-bottom: 2px solid #000;
            padding-bottom: 16px;
            margin-bottom: 32px;
            color: #000;
            font-weight: 600;
        }
        .profile-item {
            display: flex;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
            align-items: center;
        }
        .profile-item:last-child {
            border-bottom: none;
        }
        .profile-item span:first-child {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .profile-item span:last-child {
            color: #000;
            font-weight: 500;
        }

        .shop-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: #fff;
            color: #000;
            padding: 32px 24px;
            text-align: center;
            border: 1px solid #e5e5e5;
            transition: border-color 0.2s;
        }
        .stat-card:hover {
            border-color: #000;
        }
        .stat-card .value {
            font-size: 40px;
            font-weight: 600;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .stat-card .label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .admin-section {
            margin-bottom: 48px;
            background: #fff;
            padding: 32px 0;
            border-bottom: 1px solid #e5e5e5;
        }
        .admin-section h2 {
            border-bottom: 2px solid #000;
            padding-bottom: 16px;
            margin-bottom: 32px;
            color: #000;
            font-weight: 600;
        }

        .transaction-item {
            padding: 24px 0;
            border-bottom: 1px solid #e5e5e5;
            background: #fff;
            transition: background 0.2s;
        }
        .transaction-item:hover {
            background: #fafafa;
        }
        .transaction-item .details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .transaction-item .amount {
            font-weight: 600;
            color: #000;
            font-size: 18px;
        }

        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
        }
        .product-detail .image-container {
            width: 100%;
            height: 500px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            overflow: hidden;
        }
        .product-detail .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .product-detail .info h1 {
            margin-bottom: 24px;
            font-size: 36px;
            color: #000;
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .product-detail .info .price {
            font-size: 32px;
            color: #000;
            margin: 24px 0;
            font-weight: 600;
        }
        .product-detail .info .shop-link {
            display: inline-block;
            padding: 16px 32px;
            background: #000;
            color: #fff;
            text-decoration: none;
            margin-top: 24px;
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            transition: background 0.2s;
        }
        .product-detail .info .shop-link:hover {
            background: #333;
        }

        .shop-products { margin-top: 40px; }
        .shop-header {
            background: #fff;
            color: #000;
            padding: 40px 0;
            margin-bottom: 40px;
            border-bottom: 2px solid #000;
        }
        .shop-header h1 {
            font-size: 48px;
            margin-bottom: 16px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .shop-header p {
            font-size: 16px;
            color: #666;
        }

        .auth-form {
            max-width: 450px;
            margin: 80px auto;
            background: #fff;
            padding: 60px 40px;
            border-radius: 0;
            box-shadow: none;
            animation: fadeIn 0.5s ease;
            border: 1px solid #000;
            position: relative;
            overflow: hidden;
        }
        .auth-form h2 {
            text-align: center;
            margin-bottom: 40px;
            color: #000;
            font-size: 36px;
            font-weight: 700;
            text-shadow: none;
            letter-spacing: -0.02em;
        }
        .auth-form .form-group { margin-bottom: 24px; }
        .auth-form input {
            width: 100%;
            padding: 16px 0;
            border: none;
            border-bottom: 1px solid #000;
            font-size: 16px;
            transition: all 0.3s ease;
            background: transparent;
            color: #000;
            border-radius: 0;
        }
        .auth-form input:focus {
            border-color: #000;
            outline: none;
            box-shadow: none;
            background: transparent;
        }
        .auth-form input::placeholder {
            color: #999;
        }
        .auth-form button {
            width: 100%;
            padding: 16px;
            background: #000;
            color: #fff;
            border: none;
            border-radius: 0;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            margin-top: 24px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .auth-form button:hover {
            transform: translateY(-2px);
            box-shadow: none;
            color: #fff;
            background: #333;
        }
        .auth-form .links {
            text-align: center;
            margin-top: 32px;
        }
        .auth-form .links a {
            color: #000;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .auth-form .links a:hover {
            color: #666;
            text-shadow: none;
        }

        .cart-item {
            background: #fff;
            padding: 28px 32px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 2px solid #000;
            transition: all 0.2s ease;
            border-radius: 0;
        }
        .cart-item:hover {
            background: #f9f9f9;
            transform: none;
            border-color: #333;
        }
        .cart-item .item-image {
            width: 90px;
            height: 90px;
            background: #fff;
            border: 2px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 24px;
            overflow: hidden;
            flex-shrink: 0;
        }
        .cart-item .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .cart-item:hover .item-image img {
            transform: scale(1.02);
        }
        .cart-item .item-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-right: 20px;
        }
        .cart-item .item-name {
            font-weight: 700;
            color: #000;
            margin-bottom: 6px;
            text-shadow: none;
            font-size: 20px;
            line-height: 1.3;
            letter-spacing: -0.01em;
        }
        .cart-item .item-price {
            color: #000;
            font-weight: 800;
            font-size: 22px;
            text-shadow: none;
            margin-top: 6px;
            letter-spacing: -0.02em;
        }
        .cart-item .item-info > div[style*="color: #888"] {
            font-size: 13px;
            color: #666;
            margin-top: 3px;
            line-height: 1.3;
            font-weight: 400;
        }
        .cart-item .shop-name,
        .cart-item .item-attributes {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
            line-height: 1.4;
            font-weight: 400;
        }
        .cart-item .item-actions {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .cart-item .quantity-control {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .cart-item .quantity-control button {
            width: 36px;
            height: 36px;
            border-radius: 0;
            border: 2px solid #000;
            background: #fff;
            color: #000;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.2s ease;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cart-item .quantity-control button:hover {
            background: #000;
            color: #fff;
            border-color: #000;
            transform: scale(1.05);
        }
        .cart-item .quantity-control span {
            font-weight: 700;
            min-width: 36px;
            text-align: center;
            font-size: 18px;
            color: #000;
        }
        .cart-item .btn-danger {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid #000;
            background: #fff;
            color: #000;
            transition: all 0.2s ease;
        }
        .cart-item .btn-danger:hover {
            background: #000;
            color: #fff;
            border-color: #000;
        }

        .cart-items {
            margin-bottom: 40px;
        }
        .cart-item .item-info {
            padding-right: 20px;
        }
        .cart-item .item-name {
            font-size: 18px;
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }
        .cart-item .shop-name,
        .cart-item .item-attributes {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
            line-height: 1.4;
        }
        .cart-item .item-price {
            font-size: 20px;
            margin-top: 8px;
        }
        .cart-item .quantity-control button {
            font-size: 14px;
            font-weight: 700;
        }
        .cart-item .quantity-control span {
            font-size: 15px;
            color: #000;
        }
        .cart-summary {
            margin-top: 30px;
            padding: 28px;
            background: #fff;
            border: 2px solid #000;
            border-radius: 0;
        }
        .cart-summary h3 {
            font-size: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0;
        }
        .cart-summary .price-tag {
            font-size: 28px;
            font-weight: 700;
            color: #000;
            background: none;
            padding: 0;
            border-radius: 0;
            box-shadow: none;
            text-shadow: none;
        }
        .cart-summary .btn-group {
            display: flex;
            gap: 16px;
            margin-top: 24px;
        }
        .cart-summary .btn {
            flex: 1;
            padding: 18px;
            font-size: 15px;
            text-align: center;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: #fff;
            border: 1px solid #000;
        }
        .empty-state .icon {
            font-size: 60px;
            color: #000;
            margin-bottom: 24px;
            opacity: 0.8;
        }
        .empty-state p {
            font-size: 18px;
            color: #666;
            margin-bottom: 28px;
        }
        .empty-state .btn {
            padding: 16px 32px;
            font-size: 15px;
        }

        .category-tags {
            display: flex;
            gap: 8px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }
        .category-tag {
            padding: 12px 24px;
            background: #fff;
            border-radius: 0;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 400;
            border: 1px solid #000;
            color: #000;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .category-tag:hover, .category-tag.active {
            background: #000;
            color: #fff;
            transform: translateY(-2px);
            border-color: #000;
            box-shadow: none;
        }

        .reviews-section {
            margin-top: 30px;
            background: #fff;
            padding: 28px;
            border: 1px solid #000;
            border-radius: 0;
        }
        .review-item {
            padding: 20px 0;
            border-bottom: 1px solid #e5e5e5;
        }
        .review-item:last-child {
            border-bottom: none;
        }
        .review-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .review-author {
            font-weight: 600;
            color: #000;
            text-shadow: none;
        }
        .review-date {
            color: #666;
            font-size: 0.9em;
        }
        .review-content {
            color: #000;
            line-height: 1.6;
        }
        .review-rating {
            color: #000;
            font-weight: 600;
            margin-top: 8px;
            text-shadow: none;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
            backdrop-filter: none;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #fff;
            padding: 32px;
            border-radius: 0;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
            box-shadow: none;
            border: 2px solid #000;
            position: relative;
            overflow: hidden;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #000;
        }
        .modal-header h3 {
            color: #000;
            font-size: 1.5em;
            text-shadow: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #000;
            transition: all 0.2s ease;
        }
        .modal-close:hover {
            color: #666;
            transform: none;
            text-shadow: none;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .navbar {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: #fff;
                border-bottom: 1px solid #000;
                z-index: 1000;
                padding: 12px 15px;
                margin-bottom: 0;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                gap: 0;
            }
            .navbar .user-info {
                width: 100%;
                justify-content: flex-start;
                gap: 10px;
            }
            .navbar .user-info > :nth-child(3) {
                margin-left: auto;
            }
            .content {
                margin-top: 70px !important;
            }
            .product-detail {
                grid-template-columns: 1fr;
            }
            .search-box {
                flex-direction: row;
                flex-wrap: wrap;
            }
            .search-box input {
                flex: 1 0 100%;
                margin-bottom: 10px;
            }
            .search-box button {
                flex: 1;
                min-width: 120px;
            }
            .product-grid {
                grid-template-columns: 1fr;
            }
            .shop-stats {
                grid-template-columns: 1fr;
            }
            .auth-form {
                padding: 25px;
                margin: 20px;
            }
            .content {
                padding: 20px;
            }
            h1 {
                font-size: 32px;
                margin-bottom: 24px;
            }
            h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }
            h3 {
                font-size: 16px;
                margin-bottom: 12px;
            }
            .btn {
                padding: 14px 24px;
                font-size: 15px;
            }
            .form-group input, .form-group textarea, .form-group select {
                font-size: 16px; 
                padding: 14px;
            }
            .profile-section table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .file-input-custom {
                padding: 14px 20px;
                font-size: 13px;
            }
            .navbar a {
                margin: 0 0 10px 0;
                display: block;
                text-align: center;
                padding: 10px;
                border-bottom: 1px solid #eee;
            }
            .navbar .nav-links {
                display: flex;
                flex-direction: column;
                width: 100%;
            }
            .shop-header h1 {
                font-size: 32px;
                word-wrap: break-word;
                overflow-wrap: break-word;
                white-space: normal;
            }
            .shop-header p {
                font-size: 14px;
                word-wrap: break-word;
                overflow-wrap: break-word;
                white-space: normal;
            }
            .cart-items {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            .cart-item {
                min-width: 300px;
                white-space: normal;
            }
            .container {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }
            .content {
                padding: 15px;
            }
            .product-card .image-container {
                height: 200px;
            }
            .btn {
                padding: 12px 20px;
                font-size: 14px;
            }
            .file-input-custom {
                padding: 12px 16px;
                font-size: 12px;
            }
            .search-box input {
                font-size: 16px;
                padding: 14px 0;
            }
            .search-box button {
                padding: 14px 20px;
            }
            .shop-header h1 {
                font-size: 28px;
            }
            .shop-header p {
                font-size: 13px;
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state .icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.8;
            text-shadow: none;
            color: #000;
        }
        .empty-state p {
            font-size: 1.2em;
            margin-bottom: 20px;
            color: #000;
        }

        .price-tag {
            display: inline-block;
            background: #fff;
            color: #000;
            padding: 8px 16px;
            border: 1px solid #000;
            border-radius: 0;
            font-weight: 600;
            font-size: 1em;
            text-shadow: none;
            box-shadow: none;
            letter-spacing: 0.05em;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 0;
            font-size: 0.85em;
            font-weight: 600;
            border: 1px solid #000;
            background: #fff;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-badge.on-sale {
            background: #fff;
            color: #000;
            border-color: #000;
        }
        .status-badge.off-sale {
            background: #000;
            color: #fff;
            border-color: #000;
        }
        .status-badge.active {
            background: #000;
            color: #fff;
            border-color: #000;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin: 40px 0;
        }
        .stat-card-modern {
            background: #fff;
            padding: 32px 24px;
            border-radius: 0;
            box-shadow: none;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #000;
            position: relative;
            overflow: hidden;
        }
        .stat-card-modern:hover {
            transform: translateY(-4px);
            border-color: #000;
            box-shadow: none;
        }
        .stat-card-modern .value {
            font-size: 48px;
            font-weight: 700;
            color: #000;
            margin-bottom: 8px;
            text-shadow: none;
        }
        .stat-card-modern .label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75em;
            font-weight: bold;
            background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%);
            color: white;
            margin-left: 5px;
            text-shadow: 0 0 8px rgba(255, 68, 68, 0.5);
            box-shadow: 0 0 10px rgba(255, 68, 68, 0.3);
            animation: pulse 2s infinite;
        }

        .tooltip {
            position: relative;
            cursor: help;
        }
        .tooltip::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(20, 20, 35, 0.95);
            color: #00ffff;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85em;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            border: 1px solid rgba(0, 255, 255, 0.3);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.2);
            text-shadow: 0 0 8px rgba(0, 255, 255, 0.3);
        }
        .tooltip:hover::after {
            opacity: 1;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 10px rgba(255, 68, 68, 0.3); }
            50% { box-shadow: 0 0 20px rgba(255, 68, 68, 0.6); }
            100% { box-shadow: 0 0 10px rgba(255, 68, 68, 0.3); }
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-input-custom {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 32px;
            background: #fff;
            border: 1px solid #000;
            color: #000;
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .file-input-custom:hover {
            background: #000;
            color: #fff;
        }
        .file-input-custom i {
            font-size: 16px;
        }
        .file-input-wrapper small {
            display: block;
            margin-top: 8px;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .file-input-wrapper .file-name {
            margin-top: 8px;
            color: #000;
            font-size: 13px;
            font-style: italic;
        }

        .mobile-bottom-nav {
            display: none;
        }

        @media (max-width: 768px) {
            .mobile-bottom-nav {
                display: flex;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: #fff;
                border-top: 1px solid #000;
                z-index: 1000;
                padding: 10px 0;
                justify-content: space-around;
                align-items: center;
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
            }
            .mobile-bottom-nav .nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-decoration: none;
                color: #000;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                padding: 8px 12px;
                border-radius: 0;
                transition: all 0.2s;
                flex: 1;
                max-width: 25%;
            }
            .mobile-bottom-nav .nav-item i {
                font-size: 18px;
                margin-bottom: 4px;
                color: #000;
            }
            .mobile-bottom-nav .nav-item:hover,
            .mobile-bottom-nav .nav-item.active {
                color: #000;
                background: transparent;
            }
            .navbar .nav-links {
                display: none;
            }
            .content {
                padding-bottom: 80px !important;
            }
            .container {
                padding-bottom: 80px !important;
            }
        }
    </style>
    <script>
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = 'alert alert-' + type;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.animation = 'slideIn 0.3s ease';
        notification.style.padding = '16px 24px';
        notification.style.background = '#fff';
        notification.style.color = '#000';
        notification.style.borderLeft = '3px solid';
        notification.style.borderColor = type === 'error' ? '#666' : '#000';
        notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
        notification.style.maxWidth = '400px';
        notification.style.wordWrap = 'break-word';
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `;
    document.head.appendChild(style);
    </script>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="navbar">
        <div class="nav-links">
            <a href="?action=shop">购物中心</a>
            <?php if ($shop_enabled && $user && $user['has_shop']): ?>
                <a href="?action=my_shop">我的店铺</a>
            <?php endif; ?>
            <a href="?action=personal_center">个人中心</a>
            <?php if ($user && $user['is_admin']): ?>
                <a href="?action=admin">管理后台</a>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <div class="avatar">
                <?php if ($user['avatar']): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" style="width: 100%; height: 100%; border-radius: 0; object-fit: cover;">
                <?php else: ?>
                    <span style="font-size: 14px;"><?php echo substr($user['gamename'], 0, 1); ?></span>
                <?php endif; ?>
            </div>
            <span><?php echo htmlspecialchars($user['gamename']); ?></span>
            <a href="?action=cart" style="color: #000; font-weight: 600; text-shadow: none;"><i class="fas fa-shopping-cart"></i></a>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
        <div class="content">
    <?php
}

function page_footer() {
    ?>
        </div>
    </div>

    <div class="mobile-bottom-nav">
        <a href="?action=shop" class="nav-item">
            <i class="fas fa-store"></i>
            <span>购物中心</span>
        </a>
        <?php
        $user = get_logged_in_user();
        if ($user && $user['has_shop']) {
            echo '<a href="?action=my_shop" class="nav-item">
                <i class="fas fa-shop"></i>
                <span>我的店铺</span>
            </a>';
        }
        ?>
        <a href="?action=personal_center" class="nav-item">
            <i class="fas fa-user"></i>
            <span>个人中心</span>
        </a>
        <?php
        if ($user && $user['is_admin']) {
            echo '<a href="?action=admin" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>管理后台</span>
            </a>';
        }
        ?>
    </div>

</body>
</html>
    <?php
}

function show_message($message, $type = 'success') {
    echo "<div class='alert alert-{$type}' style='display:none;'>{$message}</div>";
    echo "<script>";
    echo "document.addEventListener('DOMContentLoaded', function() {";
    echo "  setTimeout(function() {";
    echo "    showNotification('" . addslashes($message) . "', '" . addslashes($type) . "');";
    echo "  }, 300);";
    echo "});";
    echo "</script>";
}

function show_login() {
    if (isset($_SESSION['user_id'])) {
        header('Location: ?action=shop');
        exit;
    }

    page_header('登录');
    ?>
    <div class="auth-form">
        <h2>用户登录</h2>
        <?php
        if (isset($_GET['error'])) {
            show_message(urldecode($_GET['error']), 'error');
        }
        ?>
        <form method="POST" action="?action=login">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="gamename" placeholder="请输入用户名" required>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" placeholder="请输入密码" required>
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%;">登录</button>
        </form>
        <div class="links">
            <p>没有账号？<a href="?action=register">点击注册</a></p>
        </div>
    </div>
    <?php
    page_footer();
}

function show_register() {
    if (isset($_SESSION['user_id'])) {
        header('Location: ?action=shop');
        exit;
    }

    $settings = get_global_settings();
    if (!$settings['allow_web_registration']) {
        show_message('网页注册已关闭，请联系管理员', 'error');
        page_header('注册');
        page_footer();
        return;
    }

    page_header('注册');
    ?>
    <div class="auth-form">
        <h2>用户注册</h2>
        <?php
        if (isset($_GET['error'])) {
            show_message(urldecode($_GET['error']), 'error');
        }
        ?>
        <form method="POST" action="?action=register">
            <div class="form-group">
                <label>用户名（gamename）</label>
                <input type="text" name="gamename" placeholder="请输入用户名（英文字母和数字）" required pattern="[A-Za-z0-9_]+" title="只能包含字母、数字和下划线">
                <small>用户名将作为您的唯一标识，不可更改。</small>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" placeholder="请输入密码" required minlength="6">
            </div>
            <div class="form-group">
                <label>确认密码</label>
                <input type="password" name="password2" placeholder="请再次输入密码" required>
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%;">注册</button>
        </form>
        <div class="links">
            <p>已有账号？<a href="?action=login">点击登录</a></p>
        </div>
    </div>
    <?php
    page_footer();
}

function handle_register() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=register');
        exit;
    }

    $settings = get_global_settings();
    if (!$settings['allow_web_registration']) {
        header('Location: ?action=register&error=' . urlencode('网页注册已关闭，请联系管理员'));
        exit;
    }

    $gamename = $_POST['gamename'] ?? '';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$gamename || !$password || !$password2) {
        header('Location: ?action=register&error=' . urlencode('请填写所有字段'));
        exit;
    }

    if ($password !== $password2) {
        header('Location: ?action=register&error=' . urlencode('两次输入的密码不一致'));
        exit;
    }

    if (strlen($password) < 6) {
        header('Location: ?action=register&error=' . urlencode('密码长度至少6位'));
        exit;
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $gamename)) {
        header('Location: ?action=register&error=' . urlencode('用户名只能包含字母、数字和下划线'));
        exit;
    }

    $existing = get_shop_user($gamename);
    if ($existing) {
        header('Location: ?action=register&error=' . urlencode('用户名已存在'));
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $new_user = [
        'gamename' => $gamename,
        'password' => $hashed_password,
        'balance' => 0.0,
        'role' => '',
        'has_shop' => false,
        'avatar' => '',
        'created_at' => date('Y-m-d H:i:s')
    ];

    $user_file = SHOP_USERS_DIR . '/' . $gamename . '.json';
    if (!is_dir(dirname($user_file))) {
        mkdir(dirname($user_file), 0775, true);
    }
    file_put_contents($user_file, json_encode($new_user, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    ensure_user_media_dirs($gamename);

    $_SESSION['user_id'] = $gamename;
    header('Location: ?action=shop');
    exit;
}

function find_shop_user_by_gamename($gamename) {
    $files = glob(SHOP_USERS_DIR . '/*.json');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $user = json_decode($content, true);
        if (is_array($user) && ($user['gamename'] ?? '') === $gamename) {
            return $user;
        }
    }
    return null;
}

function find_gamename_by_gamename($gamename) {
    $files = glob(SHOP_USERS_DIR . '/*.json');
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $user = json_decode($content, true);
        if (is_array($user) && ($user['gamename'] ?? '') === $gamename) {
            return basename($file, '.json');
        }
    }
    return null;
}

function handle_login() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=login');
        exit;
    }

    $gamename = $_POST['gamename'] ?? '';
    $password = $_POST['password'] ?? '';

    $shop_user = find_shop_user_by_gamename($gamename);
    if (!$shop_user) {
        header('Location: ?action=login&error=' . urlencode('用户名或密码错误'));
        exit;
    }

    if (!verify_password($password, $shop_user['password'] ?? '')) {
        header('Location: ?action=login&error=' . urlencode('用户名或密码错误'));
        exit;
    }
    $gamename = find_gamename_by_gamename($gamename);
    if (!$gamename) {
        header('Location: ?action=login&error=' . urlencode('用户名或密码错误'));
        exit;
    }

    $_SESSION['user_id'] = $gamename;
    header('Location: ?action=shop');
    exit;
}



function logout() {
    session_destroy();
    header('Location: ?action=login');
    exit;
}

function show_shop() {
    check_login();
    page_header('购物中心');

    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? 'all';
    $all_products = get_all_products();
    $all_categories = get_global_categories();
    $all_shops = get_all_shops();
    $user_cart = get_user_cart($_SESSION['user_id']);

    $products = [];
    foreach ($all_products as $id => $product) {
        if ($search && stripos($product['name'], $search) === false && stripos($product['description'], $search) === false) {
            continue;
        }
        if ($category !== 'all' && ($product['category'] ?? 'other') !== $category) {
            continue;
        }
        if ($product['status'] === 'on_sale' && $product['stock'] > 0) {
            $products[$id] = $product;
        }
    }

    $cart_count = count($user_cart);

    ?>
    <h1>购物中心</h1>

    <div class="category-tags">
        <?php foreach ($all_categories as $cat_id => $cat_name): ?>
            <span class="category-tag <?php echo $category === $cat_id ? 'active' : ''; ?>" data-cat="<?php echo $cat_id; ?>"
                  onclick="window.location.href = '?action=shop&category=<?php echo $cat_id; ?>&search=<?php echo urlencode($search); ?>'">
                <?php echo htmlspecialchars($cat_name); ?>
            </span>
        <?php endforeach; ?>
    </div>

    <style>
    @media (max-width: 768px) {
        .category-tags {
            overflow-x: auto;
            white-space: nowrap;
            padding-bottom: 10px;
            margin-bottom: 20px;
            -webkit-overflow-scrolling: touch;
        }

        .category-tag {
            display: inline-block;
            white-space: nowrap;
            margin-bottom: 5px;
        }
    }
    </style>

    <div class="search-box">
        <input type="text" name="search" placeholder="搜索商品名称或描述..." value="<?php echo htmlspecialchars($search); ?>">
        <button onclick="window.location.href = '?action=shop&category=<?php echo $category; ?>&search=' + encodeURIComponent(document.querySelector('.search-box input').value)">搜索</button>
        <button class="btn btn-success" onclick="window.location.href = '?action=cart'">
            购物车 <?php if ($cart_count > 0): ?><span class="badge"><?php echo $cart_count; ?></span><?php endif; ?>
        </button>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty-state">
            <div class="icon"><i class="fas fa-box"></i></div>
            <p>暂无商品</p>
            <button class="btn" onclick="window.location.href = '?action=shop'">返回全部商品</button>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $id => $product): ?>
                <div class="product-card" onclick="window.location.href = '?action=product_detail&id=<?php echo $id; ?>'">
                    <div class="image-container">
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['display_name'] ?? get_item_chinese_name($product['name'])); ?>">
                        <?php else: ?>
                            <i class="fas fa-image"></i>
                        <?php endif; ?>
                    </div>
                    <h3 style="word-wrap: break-word; overflow-wrap: break-word; max-width: 100%;"><?php echo htmlspecialchars($product['display_name'] ?? get_item_chinese_name($product['name'])); ?></h3>
                    <div style="color: #888; font-size: 0.8em; margin: 5px 24px; word-wrap: break-word; overflow-wrap: break-word; max-width: 100%;">原始名称: <?php echo htmlspecialchars($product['name']); ?></div>
                    <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                    <div class="shop-name"><i class="fas fa-store"></i> <?php
                        $shop_gamename = $product['seller_gamename'] ?? $product['shop_id'] ?? '';
                        echo htmlspecialchars($all_shops[$shop_gamename]['name'] ?? '未知店铺');
                    ?></div>
                    <div class="stock">库存: <?php echo $product['stock']; ?> 件</div>
                    <div style="margin-top: 5px;">
                        <span class="status-badge active"><?php echo htmlspecialchars($all_categories[$product['category'] ?? 'other'] ?? '其他'); ?></span>
                    </div>
                    <div class="btn-group" style="margin-top: 10px;">
                        <button class="btn btn-success" onclick="event.stopPropagation(); addToCart('<?php echo $id; ?>')">加入购物车</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <script>
    function addToCart(productId) {
        fetch('?action=add_to_cart&id=' + productId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('商品已加入购物车！', 'success');
                    updateCartCount();
                } else {
                    showNotification(data.error || '加入购物车失败', 'error');
                }
            })
            .catch(error => {
                showNotification('网络错误，请重试', 'error');
            });
    }

    function updateCartCount() {
        fetch('?action=get_cart_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const badge = document.querySelector('.badge');
                    if (badge) {
                        badge.textContent = data.count;
                    } else {
                        const btn = document.querySelector('.btn-success');
                        if (btn) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'badge';
                            newBadge.textContent = data.count;
                            btn.appendChild(newBadge);
                        }
                    }
                }
            });
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = 'alert alert-' + type;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.animation = 'slideIn 0.3s ease';
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    </script>

    <?php
    page_footer();
}

function show_product_detail() {
    check_login();

    $product_id = $_GET['id'] ?? '';
    if (!$product_id) {
        header('Location: ?action=shop');
        exit;
    }

    $all_products = get_all_products();
    $all_shops = get_all_shops();
    $all_categories = get_global_categories();

    $product = $all_products[$product_id] ?? null;

    if (!$product || $product['status'] !== 'on_sale') {
        show_message('商品不存在或已下架', 'error');
        page_header('商品详情');
        echo '<a href="?action=shop" class="btn">返回购物中心</a>';
        page_footer();
        exit;
    }

    $shop_gamename = $product['seller_gamename'] ?? $product['shop_id'] ?? '';
    $shop = $all_shops[$shop_gamename] ?? null;
    $category_name = $all_categories[$product['category'] ?? 'other'] ?? '其他';

    $reviews = [];

    page_header('商品详情 - ' . htmlspecialchars($product['name']));
    ?>
    <div class="product-detail">
        <div>
            <div class="image-container">
                <?php if (!empty($product['image'])): ?>
                    <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['display_name'] ?? get_item_chinese_name($product['name'])); ?>">
                <?php else: ?>
                    <i class="fas fa-image"></i>
                <?php endif; ?>
            </div>
        </div>
        <div class="info">
            <h1 style="word-wrap: break-word; overflow-wrap: break-word; max-width: 100%;"><?php echo htmlspecialchars($product['display_name'] ?? get_item_chinese_name($product['name'])); ?></h1>
            <div style="color: #888; font-size: 0.9em; margin-top: 5px;">原始名称: <?php echo htmlspecialchars($product['name']); ?></div>
            <?php if (!empty($product['nbt'])): ?>
                <div class="item-attributes" style="word-wrap: break-word; overflow-wrap: break-word; max-width: 100%;"><?php echo htmlspecialchars(parse_nbt_to_attributes($product['nbt'], $product['name'])); ?></div>
            <?php endif; ?>
            <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
            <p style="word-wrap: break-word; overflow-wrap: break-word; max-width: 100%;"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            <div style="margin-top: 15px;">
                <span class="status-badge <?php echo $product['stock'] > 0 ? 'on-sale' : 'off-sale'; ?>">
                    库存: <?php echo $product['stock']; ?> 件
                </span>
                <span class="status-badge active" style="margin-left: 10px;">
                    <?php echo htmlspecialchars($category_name); ?>
                </span>
            </div>
            <p style="color: #666; margin-top: 10px;">上架时间: <?php echo $product['created_at']; ?></p>

            <?php if ($shop): ?>
                <a href="?action=shop_detail&id=<?php echo $product['shop_id']; ?>" class="shop-link">查看店铺: <?php echo htmlspecialchars($shop['name']); ?></a>
            <?php endif; ?>

            <div class="btn-group" style="margin-top: 20px;">
                <button class="btn btn-success" onclick="addToCart('<?php echo $product_id; ?>')">加入购物车</button>
                <button class="btn btn-success" onclick="window.location.href = '?action=buy_product&id=<?php echo $product_id; ?>'">立即购买</button>
                <button class="btn btn-secondary" onclick="window.location.href = '?action=shop'">返回购物中心</button>
            </div>
        </div>
    </div>

    <script>
    function addToCart(productId) {
        fetch('?action=add_to_cart&id=' + productId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('商品已加入购物车！', 'success');
                    updateCartCount();
                } else {
                    showNotification(data.error || '加入购物车失败', 'error');
                }
            })
            .catch(error => {
                showNotification('网络错误，请重试', 'error');
            });
    }

    function updateCartCount() {
        fetch('?action=get_cart_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const badge = document.querySelector('.badge');
                    if (badge) {
                        badge.textContent = data.count;
                    }
                }
            });
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = 'alert alert-' + type;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.animation = 'slideIn 0.3s ease';
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    </script>

    <?php
    page_footer();
}

function buy_product() {
    check_login();

    $product_id = $_GET['id'] ?? '';
    if (!$product_id) {
        header('Location: ?action=shop');
        exit;
    }

    $all_products = get_all_products();
    $product = $all_products[$product_id] ?? null;
    $user = get_logged_in_user();
    $settings = get_global_settings();

    if (!$product || $product['status'] !== 'on_sale') {
        show_message('商品不存在或已下架', 'error');
        page_header('购买失败');
        echo '<a href="?action=shop" class="btn">返回购物中心</a>';
        page_footer();
        exit;
    }

    if ($product['stock'] <= 0) {
        show_message('商品库存不足', 'error');
        page_header('购买失败');
        echo '<a href="?action=shop" class="btn">返回购物中心</a>';
        page_footer();
        exit;
    }

    $total_price = $product['price'];
    $fee = $total_price * $settings['transaction_fee'];
    $final_price = $total_price + $fee;

    $inventory = get_user_inventory($_SESSION['user_id']);
    $current_inventory_count = count($inventory);

    if ($current_inventory_count >= 9) {
        show_message('物品栏已满，最多只能有9种不同的物品', 'error');
        page_header('购买失败');
        echo '<a href="?action=shop" class="btn">返回购物中心</a>';
        page_footer();
        exit;
    }

    $buyer_id = $_SESSION['user_id'];
    $buyer_balance = get_user_balance($buyer_id);
    if ($buyer_balance < $final_price) {
        show_message('余额不足，无法购买。请先充值或减少购买内容。', 'error');
        page_header('购买失败');
        echo '<a href="?action=shop" class="btn">返回购物中心</a>';
        page_footer();
        exit;
    }

    $ok = change_user_balance($buyer_id, -$final_price);
    if (!$ok) {
        show_message('扣款失败，余额可能不足', 'error');
        page_header('购买失败');
        echo '<a href="?action=shop" class="btn">返回购物中心</a>';
        page_footer();
        exit;
    }

    $new_stock = $product['stock'] - 1;
    $seller_gamename = $product['seller_gamename'] ?? $product['shop_id'] ?? '';
    if ($seller_gamename) {
        $product['stock'] = $new_stock;
        if ($new_stock <= 0) {
            $product['status'] = 'off_sale';
        }
        save_product($seller_gamename, $product_id, $product);
    }

    if ($seller_gamename) {
        change_user_balance($seller_gamename, $total_price);
    }

    $found = false;
    $product_nbt = $product['nbt'] ?? '';
    foreach ($inventory as $existing_id => $existing_item) {
        $existing_nbt = $existing_item['nbt'] ?? '';
        if ($existing_item['name'] === $product['name'] && $existing_nbt === $product_nbt) {
            $existing_item['quantity'] += 1;
            save_inventory_item($_SESSION['user_id'], $existing_id, $existing_item);
            $found = true;
            break;
        }
    }

    if (!$found) {
        $item_id = uniqid();
        $new_item = [
            'name' => $product['name'],
            'quantity' => 1,
            'nbt' => $product_nbt
        ];
        save_inventory_item($_SESSION['user_id'], $item_id, $new_item);
    }

    $transaction_id = uniqid();
    $transaction = [
        'buyer_id' => $_SESSION['user_id'],
        'items' => [
            [
                'product_id' => $product_id,
                'product_name' => $product['name'],
                'price' => $product['price'],
                'quantity' => 1,
                'seller_id' => $seller_gamename
            ]
        ],
        'total_price' => $total_price,
        'fee' => $fee,
        'final_price' => $final_price,
        'status' => 'completed',
        'created_at' => date('Y-m-d H:i:s')
    ];
    save_transaction($_SESSION['user_id'], $transaction_id, $transaction);


    show_message('购买成功！商品价格 $' . number_format($product['price'], 2) . '（含手续费 $' . number_format($fee, 2) . '）', 'success');
    page_header('购买成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <div style="font-size: 4em; margin-bottom: 20px;">✅</div>
        <h2>购买成功！</h2>
        <p>商品: <?php echo htmlspecialchars($product['name']); ?></p>
        <p>商品价格: $<?php echo number_format($product['price'], 2); ?></p>
        <p>手续费: $<?php echo number_format($fee, 2); ?></p>
        <p style="font-size: 1.2em; font-weight: bold; color: #27ae60;">总计: $<?php echo number_format($final_price, 2); ?></p>
        <div class="btn-group" style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=shop'">继续购物</button>
            <button class="btn btn-secondary" onclick="window.location.href = '?action=personal_center'">查看个人中心</button>
        </div>
    </div>
    <?php
    page_footer();
}

function show_shop_detail() {
    check_login();

    $shop_id = $_GET['id'] ?? '';
    if (!$shop_id) {
        header('Location: ?action=shop');
        exit;
    }

    $shops = get_all_shops();
    $shop = $shops[$shop_id] ?? null;

    if (!$shop) {
        show_message('店铺不存在', 'error');
        page_header('店铺详情');
        echo '<a href="?action=shop" class="btn">返回购物中心</a>';
        page_footer();
        exit;
    }

    $owner = get_shop_user($shop_id);
    $owner_gamename = $owner['gamename'] ?? '未知';

    $all_products = get_all_products();
    $products = [];
    foreach ($all_products as $id => $product) {
        if ($product['seller_id'] === $shop_id && $product['status'] === 'on_sale' && $product['stock'] > 0) {
            $products[$id] = $product;
        }
    }

    $reviews = get_user_feedbacks($shop_id);

    $avg_rating = 0;
    if (!empty($reviews)) {
        $total_rating = 0;
        foreach ($reviews as $review) {
            $total_rating += $review['rating'];
        }
        $avg_rating = round($total_rating / count($reviews), 1);
    }

    $categories = get_global_categories();

    page_header('店铺 - ' . htmlspecialchars($shop['name']));
    ?>
    <div class="shop-header">
        <h1><?php echo htmlspecialchars($shop['name']); ?></h1>
        <p><?php echo htmlspecialchars($shop['description']); ?></p>
        <p style="margin-top: 10px;">店主: <?php echo htmlspecialchars($owner_gamename); ?></p>
    </div>

    <h2>店铺商品</h2>
    <?php if (empty($products)): ?>
        <div class="empty-state">
            <div class="icon"><i class="fas fa-box"></i></div>
            <p>该店铺暂无商品</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $id => $product): ?>
                <div class="product-card" onclick="window.location.href = '?action=product_detail&id=<?php echo $id; ?>'">
                    <div class="image-container">
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['display_name'] ?? get_item_chinese_name($product['name'])); ?>">
                        <?php else: ?>
                            <i class="fas fa-image"></i>
                        <?php endif; ?>
                    </div>
                    <h3 style="word-wrap: break-word; overflow-wrap: break-word; max-width: 100%;"><?php echo htmlspecialchars($product['display_name'] ?? get_item_chinese_name($product['name'])); ?></h3>
                    <div style="color: #888; font-size: 0.8em; margin: 5px 24px; word-wrap: break-word; overflow-wrap: break-word; max-width: 100%;">原始名称: <?php echo htmlspecialchars($product['name']); ?></div>
                    <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                    <div class="stock">库存: <?php echo $product['stock']; ?> 件</div>
                    <div style="margin-top: 5px;">
                        <span class="status-badge active"><?php echo htmlspecialchars($categories[$product['category'] ?? 'other'] ?? '其他'); ?></span>
                    </div>
                    <div class="btn-group" style="margin-top: 10px;">
                        <button class="btn btn-success" onclick="event.stopPropagation(); addToCart('<?php echo $id; ?>')">加入购物车</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="margin-top: 30px;">
        <h2>店铺评价</h2>
        <p>平均评分: <?php echo $avg_rating; ?> 星 (共 <?php echo count($reviews); ?> 条评价)</p>

        <div style="margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
            <h3>添加评价</h3>
            <form method="POST" action="?action=add_review&id=<?php echo $shop_id; ?>">
                <div class="form-group">
                    <label>评分</label>
                    <select name="rating" required>
                        <option value="5">⭐⭐⭐⭐⭐ 5星</option>
                        <option value="4">⭐⭐⭐⭐ 4星</option>
                        <option value="3">⭐⭐⭐ 3星</option>
                        <option value="2">⭐⭐ 2星</option>
                        <option value="1">⭐ 1星</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>评价内容</label>
                    <textarea name="content" rows="3" required placeholder="分享您的购物体验..."></textarea>
                </div>
                <button type="submit" class="btn btn-success">提交评价</button>
            </form>
        </div>

        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <div class="icon"><i class="fas fa-comment"></i></div>
                <p>暂无评价</p>
            </div>
        <?php else: ?>
            <?php foreach ($reviews as $review_id => $review): ?>
                <div class="review-item" style="margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="font-weight: bold;"><?php echo htmlspecialchars($review['gamename']); ?></span>
                        <span style="color: #666;"><?php echo $review['created_at']; ?></span>
                    </div>
                    <div style="color: #f39c12; margin-bottom: 5px;">
                        <?php for ($i = 0; $i < $review['rating']; $i++): ?>⭐<?php endfor; ?>
                    </div>
                    <div><?php echo nl2br(htmlspecialchars($review['content'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    function addToCart(productId) {
        fetch('?action=add_to_cart&id=' + productId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('商品已加入购物车！', 'success');
                    updateCartCount();
                } else {
                    showNotification(data.error || '加入购物车失败', 'error');
                }
            })
            .catch(error => {
                showNotification('网络错误，请重试', 'error');
            });
    }

    function updateCartCount() {
        fetch('?action=get_cart_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const badge = document.querySelector('.badge');
                    if (badge) {
                        badge.textContent = data.count;
                    }
                }
            });
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = 'alert alert-' + type;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.animation = 'slideIn 0.3s ease';
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    </script>

    <div style="margin-top: 20px;">
        <button class="btn" onclick="window.location.href = '?action=shop'">返回购物中心</button>
    </div>
    <?php
    page_footer();
}

function show_personal_center() {
    check_login();

    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        echo "<div class='alert alert-{$type}' style='display:none;'>{$message}</div>";
        echo "<script>";
        echo "document.addEventListener('DOMContentLoaded', function() {";
        echo "  setTimeout(function() {";
        echo "    showNotification('" . addslashes($message) . "', '" . addslashes($type) . "');";
        echo "  }, 300);";
        echo "});";
        echo "</script>";
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }

    $user = get_logged_in_user();
    $settings = get_global_settings();
    $anchor_item = $settings['anchor_item'] ?? 'minecraft:iron_ingot';
    $anchor_display = get_item_chinese_name($anchor_item);
    $cart = get_user_cart($_SESSION['user_id']);
    $cart_count = count($cart);

    page_header('个人中心');
    ?>
    <h1>个人中心</h1>

    <div class="profile-section">
        <h2>账户信息</h2>
        <div class="profile-item">
            <span>用户名</span>
            <span><?php echo htmlspecialchars($user['gamename']); ?></span>
        </div>
        <div class="profile-item">
            <span>店铺状态</span>
            <span><?php echo $user['has_shop'] ? '已开通' : '未开通'; ?></span>
        </div>
        <div class="profile-item">
            <span>余额</span>
            <span>$<?php echo number_format($user['balance'] ?? 0, 2); ?></span>
        </div>
        <div class="profile-item">
            <span>购物车</span>
            <span><?php echo $cart_count; ?> 件商品</span>
        </div>
        <div class="btn-group" style="margin-top: 15px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=cart'">查看购物车</button>
            <button class="btn" onclick="window.location.href='?action=recharge'">充值（<?php echo htmlspecialchars($anchor_display); ?>兑换余额）</button>
            <button class="btn" onclick="window.location.href='?action=withdraw'">提现（余额兑换<?php echo htmlspecialchars($anchor_display); ?>）</button>
            <button class="btn btn-danger" onclick="window.location.href='?action=logout'">退出登录</button>
        </div>
    </div>

    <div class="profile-section">
        <h2>头像管理</h2>
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
            <div style="width: 80px; height: 80px; border-radius: 0; background: #000; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                <?php if ($user['avatar']): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span style="color: #fff; font-size: 24px;"><?php echo substr($user['gamename'], 0, 1); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <p style="color: #666; margin-bottom: 10px;">上传新头像（支持 JPG、PNG、GIF、WebP 格式，最大 5MB）</p>
                <form method="POST" action="?action=change_avatar" enctype="multipart/form-data">
                    <div class="file-input-wrapper" style="margin-bottom: 10px;">
                        <input type="file" name="avatar" id="avatar-input" accept="image/*">
                        <div class="file-input-custom">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>选择图片文件</span>
                        </div>
                        <small>支持 JPG、PNG 格式，最大 5MB</small>
                        <div class="file-name" style="display: none;"></div>
                    </div>
                    <button type="submit" class="btn btn-success">上传头像</button>
                </form>
            </div>
        </div>
    </div>

    <div class="profile-section">
        <h2>我的物品栏</h2>
        <?php
        $inventory = $user['inventory'] ?? [];
        if (empty($inventory)):
        ?>
            <p style="color: #666; padding: 20px; text-align: center;">物品栏为空</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>物品名称</th>
                        <th>数量</th>
                        <th>物品属性</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item_id => $item): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars(get_item_chinese_name($item['name'] ?? '')); ?>
                                <div style="color: #888; font-size: 0.8em; margin-top: 3px;">原始名称: <?php echo htmlspecialchars($item['name'] ?? ''); ?></div>
                            </td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo !empty($item['nbt']) ? htmlspecialchars(parse_nbt_to_attributes($item['nbt'], $item['name'] ?? '')) : ''; ?></td>
                            <td>
                                <button class="btn" onclick="window.location.href = '?action=add_to_shop&item_id=<?php echo $item_id; ?>'">上架到店铺</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="profile-section">
        <h2>交易记录</h2>
        <?php
        $transactions = get_user_transactions($_SESSION['user_id']);

        uasort($transactions, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        $recent_transactions = array_slice($transactions, 0, 2);

        if (empty($recent_transactions)) {
            echo '<div class="empty-state"><div class="icon"><i class="fas fa-clipboard-list"></i></div><p>暂无交易记录</p></div>';
        } else {
            foreach ($recent_transactions as $id => $transaction) {
                ?>
                <div class="transaction-item">
                    <div class="details">
                        <span>
                            <?php
                            if (isset($transaction['items'])) {
                                $item_names = [];
                                foreach ($transaction['items'] as $item) {
                                    $item_names[] = $item['product_name'] . ' × ' . $item['quantity'];
                                }
                                echo htmlspecialchars(implode(', ', $item_names));
                            } else {
                                echo htmlspecialchars($transaction['product_name'] ?? '未知商品');
                            }
                            ?>
                        </span>
                        <span class="amount">-$<?php echo number_format($transaction['final_price'] ?? $transaction['total'] ?? 0, 2); ?></span>
                    </div>
                    <div style="color: #999; font-size: 0.9em; margin-top: 5px;">
                        <?php echo $transaction['created_at']; ?> |
                        手续费: $<?php echo number_format($transaction['fee'] ?? 0, 2); ?> |
                        总价: $<?php echo number_format($transaction['total_price'] ?? $transaction['price'] ?? 0, 2); ?>
                    </div>
                </div>
                <?php
            }

            if (count($transactions) > 2) {
                ?>
                <div style="margin-top: 15px; text-align: center;">
                    <button class="btn" onclick="window.location.href = '?action=transactions'">查看全部</button>
                </div>
                <?php
            }
        }
        ?>
    </div>

    <div class="profile-section">
        <h2>我想开店</h2>
        <?php if ($user['has_shop']): ?>
            <p style="color: #27ae60; padding: 20px; text-align: center;">您已开通店铺！</p>
            <div style="text-align: center;">
                <button class="btn btn-success" onclick="window.location.href = '?action=my_shop'">进入我的店铺</button>
            </div>
        <?php else: ?>
            <?php if ($settings['shop_enabled']): ?>
                <p style="color: #666; padding: 20px; text-align: center;">开通店铺需要支付 $<?php echo number_format($settings['shop_opening_fee'], 2); ?> 的开店费</p>
                <div style="text-align: center;">
                    <button class="btn btn-success" onclick="window.location.href = '?action=open_shop'">立即开通店铺</button>
                </div>
            <?php else: ?>
                <p style="color: #666; padding: 20px; text-align: center;">店铺功能暂未开放</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php
    page_footer();
}

function show_recharge() {
    check_login();

    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        echo "<div class='alert alert-{$type}' style='display:none;'>{$message}</div>";
        echo "<script>";
        echo "document.addEventListener('DOMContentLoaded', function() {";
        echo "  setTimeout(function() {";
        echo "    showNotification('" . addslashes($message) . "', '" . addslashes($type) . "');";
        echo "  }, 300);";
        echo "});";
        echo "</script>";
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }

    $user = get_logged_in_user();
    $settings = get_global_settings();
    $anchor_item = $settings['anchor_item'] ?? 'minecraft:iron_ingot';
    $exchange_rate = floatval($settings['exchange_rate'] ?? 1.0);
    $anchor_display = get_item_chinese_name($anchor_item);
    page_header('充值（' . $anchor_display . '兑换余额）');
    ?>
    <div class="profile-section">
        <h1><i class="fas fa-coins"></i> 充值（<?php echo htmlspecialchars($anchor_display); ?>兑换余额）</h1>
        <div class="exchange-summary" style="background: #f8f8f8; padding: 24px; margin-bottom: 32px; border-left: 4px solid #000;">
            <p style="font-size: 16px; margin-bottom: 12px;"><strong>当前余额：</strong>$<?php echo number_format($user['balance'] ?? 0, 2); ?></p>
            <p style="font-size: 14px; color: #666;">每个 <?php echo htmlspecialchars($anchor_display); ?> 可兑换 <strong><?php echo number_format($exchange_rate, 2); ?></strong> 单位余额。</p>
        </div>
        <form id="recharge-form" method="POST" action="?action=handle_recharge" style="max-width: 500px;" onsubmit="return handleRecharge(event)">
            <div class="form-group">
                <label><i class="fas fa-cube"></i> 要兑换的<?php echo htmlspecialchars($anchor_display); ?>数量</label>
                <input type="number" name="item_count" min="1" step="1" placeholder="例如：10" required />
                <small style="color: #666;">请输入整数，系统将从您的物品栏扣除相应数量的<?php echo htmlspecialchars($anchor_display); ?>。</small>
            </div>
            <div class="form-group" style="margin-top: 24px;">
                <div style="display: flex; gap: 16px; align-items: center;">
                    <button class="btn btn-success" type="submit" style="flex: 1;" id="recharge-submit">
                        <i class="fas fa-exchange-alt"></i> 立即兑换
                    </button>
                    <a class="btn btn-secondary" href="?action=personal_center" style="flex: 1; text-align: center;">
                        <i class="fas fa-arrow-left"></i> 返回个人中心
                    </a>
                </div>
            </div>
        </form>
        <script>
        function handleRecharge(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = document.getElementById('recharge-submit');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
            submitBtn.disabled = true;

            const formData = new FormData(form);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showNotification(response.message, 'success');
                            const balanceElements = document.querySelectorAll('.exchange-summary p strong');
                            if (balanceElements.length > 0) {
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1500);
                            }
                        } else {
                            showNotification(response.error, 'error');
                        }
                    } catch (e) {
                        showNotification('服务器响应异常', 'error');
                    }
                } else {
                    showNotification('网络请求失败', 'error');
                }
            };
            xhr.onerror = function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showNotification('网络错误，请重试', 'error');
            };
            xhr.send(formData);
            return false;
        }
        </script>
        <div class="info-box" style="margin-top: 40px; padding: 20px; background: #fff; border: 1px solid #e5e5e5; font-size: 14px; color: #666;">
            <h3 style="font-size: 16px; margin-bottom: 12px;"><i class="fas fa-info-circle"></i> 充值说明</h3>
            <ul style="padding-left: 20px;">
                <li>充值过程会从您的物品栏中扣除对应数量的<?php echo htmlspecialchars($anchor_display); ?>。</li>
                <li>兑换后的余额将立即到账，可用于购物或提现。</li>
                <li>如果物品栏中<?php echo htmlspecialchars($anchor_display); ?>不足，充值将失败。</li>
                <li>汇率由系统管理员设定，目前为 <strong><?php echo number_format($exchange_rate, 2); ?> 余额/<?php echo htmlspecialchars($anchor_display); ?></strong>。</li>
            </ul>
        </div>
    </div>
    <?php
    page_footer();
}

function handle_recharge() {
    check_login();
    $user_id = $_SESSION['user_id'];
    $settings = get_global_settings();
    $anchor_item = $settings['anchor_item'] ?? 'minecraft:iron_ingot';
    $exchange_rate = floatval($settings['exchange_rate'] ?? 1.0);
    $anchor_display = get_item_chinese_name($anchor_item);

    $item_count = intval($_POST['item_count'] ?? 0);
    if ($item_count <= 0) {
        $error = '无效的数量';
        if (is_ajax_request()) {
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
        $_SESSION['flash_message'] = $error;
        $_SESSION['flash_type'] = 'error';
        header('Location: ?action=recharge');
        exit;
    }

    $inventory = get_user_inventory($user_id);
    $available = 0;
    foreach ($inventory as $iid => $it) {
        if (is_anchor_item($it['name'] ?? '')) {
            $available += intval($it['quantity'] ?? 0);
        }
    }
    if ($available < $item_count) {
        $error = '物品栏中' . $anchor_display . '不足';
        if (is_ajax_request()) {
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
        $_SESSION['flash_message'] = $error;
        $_SESSION['flash_type'] = 'error';
        header('Location: ?action=recharge');
        exit;
    }

    $to_consume = $item_count;
    foreach ($inventory as $iid => $it) {
        if ($to_consume <= 0) break;
        if (!is_anchor_item($it['name'] ?? '')) continue;
        $qty = intval($it['quantity'] ?? 0);
        if ($qty <= $to_consume) {
            delete_inventory_item($user_id, $iid);
            $to_consume -= $qty;
        } else {
            $it['quantity'] = $qty - $to_consume;
            save_inventory_item($user_id, $iid, $it);
            $to_consume = 0;
            break;
        }
    }

    if ($to_consume > 0) {
        $error = '扣除' . $anchor_display . '失败：物品栏变动异常，请重试';
        if (is_ajax_request()) {
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
        $_SESSION['flash_message'] = $error;
        $_SESSION['flash_type'] = 'error';
        header('Location: ?action=recharge');
        exit;
    }

    $balance_to_add = $item_count * $exchange_rate;
    $ok = change_user_balance($user_id, $balance_to_add);
    if (!$ok) {
        $error = '充值失败：无法写入余额，请稍后重试';
        if (is_ajax_request()) {
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
        $_SESSION['flash_message'] = $error;
        $_SESSION['flash_type'] = 'error';
        header('Location: ?action=recharge');
        exit;
    }

    $success_message = '充值成功：已兑换 ' . $item_count . ' 个' . $anchor_display . '为 ' . number_format($balance_to_add, 2) . ' 余额';
    if (is_ajax_request()) {
        $new_balance = get_user_balance($user_id);
        echo json_encode([
            'success' => true,
            'message' => $success_message,
            'new_balance' => $new_balance,
            'balance_to_add' => $balance_to_add
        ]);
        exit;
    }
    $_SESSION['flash_message'] = $success_message;
    $_SESSION['flash_type'] = 'success';
    header('Location: ?action=personal_center');
    exit;
}

function show_withdraw() {
    check_login();

    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        echo "<div class='alert alert-{$type}' style='display:none;'>{$message}</div>";
        echo "<script>";
        echo "document.addEventListener('DOMContentLoaded', function() {";
        echo "  setTimeout(function() {";
        echo "    showNotification('" . addslashes($message) . "', '" . addslashes($type) . "');";
        echo "  }, 300);";
        echo "});";
        echo "</script>";
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }

    $user = get_logged_in_user();
    $settings = get_global_settings();
    $anchor_item = $settings['anchor_item'] ?? 'minecraft:iron_ingot';
    $exchange_rate = floatval($settings['exchange_rate'] ?? 1.0);
    $anchor_display = get_item_chinese_name($anchor_item);
    page_header('提现（余额兑换' . $anchor_display . '）');
    ?>
    <div class="profile-section">
        <h1><i class="fas fa-wallet"></i> 提现（余额兑换<?php echo htmlspecialchars($anchor_display); ?>）</h1>
        <div class="exchange-summary" style="background: #f8f8f8; padding: 24px; margin-bottom: 32px; border-left: 4px solid #000;">
            <p style="font-size: 16px; margin-bottom: 12px;"><strong>当前余额：</strong>$<?php echo number_format($user['balance'] ?? 0, 2); ?></p>
            <p style="font-size: 14px; color: #666;">每 <strong><?php echo number_format($exchange_rate, 2); ?></strong> 单位余额可兑换为 1 个 <?php echo htmlspecialchars($anchor_display); ?>。</p>
        </div>
        <form id="withdraw-form" method="POST" action="?action=handle_withdraw" style="max-width: 500px;" onsubmit="return handleWithdraw(event)">
            <div class="form-group">
                <label><i class="fas fa-cube"></i> 要提现的<?php echo htmlspecialchars($anchor_display); ?>数量</label>
                <input type="number" name="item_count" min="1" step="1" placeholder="例如：5" required />
                <small style="color: #666;">请输入整数，系统将从您的余额扣除相应金额并发放<?php echo htmlspecialchars($anchor_display); ?>到物品栏。</small>
            </div>
            <div class="form-group" style="margin-top: 24px;">
                <div style="display: flex; gap: 16px; align-items: center;">
                    <button class="btn btn-success" type="submit" style="flex: 1;" id="withdraw-submit">
                        <i class="fas fa-exchange-alt"></i> 立即提现
                    </button>
                    <a class="btn btn-secondary" href="?action=personal_center" style="flex: 1; text-align: center;">
                        <i class="fas fa-arrow-left"></i> 返回个人中心
                    </a>
                </div>
            </div>
        </form>
        <script>
        function handleWithdraw(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = document.getElementById('withdraw-submit');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
            submitBtn.disabled = true;

            const formData = new FormData(form);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showNotification(response.message, 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            showNotification(response.error, 'error');
                        }
                    } catch (e) {
                        showNotification('服务器响应异常', 'error');
                    }
                } else {
                    showNotification('网络请求失败', 'error');
                }
            };
            xhr.onerror = function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showNotification('网络错误，请重试', 'error');
            };
            xhr.send(formData);
            return false;
        }
        </script>
        <div class="info-box" style="margin-top: 40px; padding: 20px; background: #fff; border: 1px solid #e5e5e5; font-size: 14px; color: #666;">
            <h3 style="font-size: 16px; margin-bottom: 12px;"><i class="fas fa-info-circle"></i> 提现说明</h3>
            <ul style="padding-left: 20px;">
                <li>提现过程会从您的余额中扣除相应金额（数量 × 汇率）。</li>
                <li>兑换后的<?php echo htmlspecialchars($anchor_display); ?>将添加到您的物品栏中。</li>
                <li>如果余额不足，提现将失败。</li>
                <li>汇率由系统管理员设定，目前为 <strong><?php echo number_format($exchange_rate, 2); ?> 余额/<?php echo htmlspecialchars($anchor_display); ?></strong>。</li>
            </ul>
        </div>
    </div>
    <?php
    page_footer();
}

function handle_withdraw() {
    check_login();
    $user_id = $_SESSION['user_id'];
    $settings = get_global_settings();
    $anchor_item = $settings['anchor_item'] ?? 'minecraft:iron_ingot';
    $exchange_rate = floatval($settings['exchange_rate'] ?? 1.0);
    $anchor_display = get_item_chinese_name($anchor_item);

    $item_count = intval($_POST['item_count'] ?? 0);
    if ($item_count <= 0) {
        $error = '无效的数量';
        if (is_ajax_request()) {
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
        $_SESSION['flash_message'] = $error;
        $_SESSION['flash_type'] = 'error';
        header('Location: ?action=withdraw');
        exit;
    }

    $balance_needed = $item_count * $exchange_rate;

    $balance = get_user_balance($user_id);
    if ($balance < $balance_needed) {
        $error = '余额不足';
        if (is_ajax_request()) {
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
        $_SESSION['flash_message'] = $error;
        $_SESSION['flash_type'] = 'error';
        header('Location: ?action=withdraw');
        exit;
    }

    $ok = change_user_balance($user_id, -$balance_needed);
    if (!$ok) {
        $error = '扣款失败';
        if (is_ajax_request()) {
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
        $_SESSION['flash_message'] = $error;
        $_SESSION['flash_type'] = 'error';
        header('Location: ?action=withdraw');
        exit;
    }

    $inventory = get_user_inventory($user_id);
    $found = false;
    foreach ($inventory as $iid => $it) {
        if (is_anchor_item($it['name'] ?? '') && (($it['nbt'] ?? '') === '')) {
            $it['quantity'] = intval($it['quantity'] ?? 0) + $item_count;
            save_inventory_item($user_id, $iid, $it);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $new_id = uniqid();
        $new_item = ['name' => $anchor_item, 'quantity' => $item_count, 'nbt' => ''];
        save_inventory_item($user_id, $new_id, $new_item);
    }

    $new_inventory = get_user_inventory($user_id);
    $total_anchor = 0;
    foreach ($new_inventory as $iid => $it) {
        if (is_anchor_item($it['name'] ?? '')) {
            $total_anchor += intval($it['quantity'] ?? 0);
        }
    }
    if ($total_anchor <= 0) {
        change_user_balance($user_id, $balance_needed);
        $error = '提现失败：无法写入物品栏，已回滚余额，请联系管理员';
        if (is_ajax_request()) {
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
        $_SESSION['flash_message'] = $error;
        $_SESSION['flash_type'] = 'error';
        header('Location: ?action=withdraw');
        exit;
    }

    $success_message = '提现成功：已兑换 ' . $item_count . ' 个' . $anchor_display . '，消耗 ' . number_format($balance_needed, 2) . ' 余额';
    if (is_ajax_request()) {
        $new_balance = get_user_balance($user_id);
        echo json_encode([
            'success' => true,
            'message' => $success_message,
            'new_balance' => $new_balance,
            'balance_needed' => $balance_needed,
            'anchor_added' => $item_count
        ]);
        exit;
    }
    $_SESSION['flash_message'] = $success_message;
    $_SESSION['flash_type'] = 'success';
    header('Location: ?action=personal_center');
    exit;
}




function show_add_to_shop() {
    check_login();
    check_shop_permission();

    $item_id = $_GET['item_id'] ?? '';
    if (!$item_id) {
        show_message('无效请求：未指定物品', 'error');
        page_header('上架物品');
        echo '<a href="?action=personal_center" class="btn">返回个人中心</a>';
        page_footer();
        exit;
    }

    $user = get_logged_in_user();
    $user_id = $_SESSION['user_id'];
    $inventory = get_user_inventory($user_id);

    if (!isset($inventory[$item_id])) {
        show_message('物品不存在', 'error');
        page_header('上架物品');
        echo '<a href="?action=personal_center" class="btn">返回个人中心</a>';
        page_footer();
        exit;
    }

    $item = $inventory[$item_id];
    $categories = get_global_categories();

    page_header('上架物品 - ' . htmlspecialchars(get_item_chinese_name($item['name'])));
    ?>
    <h1>上架物品</h1>

    <div style="margin-bottom: 20px; padding: 24px; background: #fff; border: 1px solid #000;">
        <h3 style="margin-bottom: 16px; font-size: 16px; text-transform: uppercase; letter-spacing: 0.1em;">物品信息</h3>
        <p><strong>中文名称:</strong> <?php echo htmlspecialchars(get_item_chinese_name($item['name'])); ?></p>
        <p><strong>原始名称:</strong> <?php echo htmlspecialchars($item['name']); ?></p>
        <?php if (!empty($item['nbt'])): ?>
            <p><strong>物品属性:</strong> <?php echo htmlspecialchars(parse_nbt_to_attributes($item['nbt'], $item['name'])); ?></p>
        <?php endif; ?>
        <p><strong>拥有数量:</strong> <?php echo $item['quantity']; ?> 个</p>
    </div>

    <form method="POST" action="?action=handle_add_to_shop&item_id=<?php echo $item_id; ?>" enctype="multipart/form-data">
        <div class="form-group">
            <label>商品显示名称（可修改）</label>
            <input type="text" name="display_name" value="<?php echo htmlspecialchars(get_item_chinese_name($item['name'])); ?>" required>
        </div>
        <div class="form-group">
            <label>商品描述</label>
            <textarea name="description" rows="4" required></textarea>
        </div>
        <div class="form-group">
            <label>商品分类</label>
            <select name="category" required>
                <?php foreach ($categories as $cat_id => $cat_name): ?>
                    <?php if ($cat_id !== 'all'): ?>
                        <option value="<?php echo $cat_id; ?>"><?php echo htmlspecialchars($cat_name); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>价格</label>
            <input type="number" name="price" step="0.01" min="0" required>
        </div>
        <div class="form-group">
            <label>上架数量（库存）</label>
            <input type="number" name="stock" min="1" max="<?php echo $item['quantity']; ?>" required>
            <small style="color: #666;">当前拥有: <?php echo $item['quantity']; ?> 个</small>
        </div>
        <div class="form-group">
            <label>图片上传（可选）</label>
            <div class="file-input-wrapper">
                <input type="file" name="image" accept="image/*">
                <div class="file-input-custom">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>选择图片文件</span>
                </div>
                <small>支持 JPG、PNG 格式，最大 5MB</small>
                <div class="file-name" style="display: none;"></div>
            </div>
        </div>
        <div class="btn-group">
            <button type="submit" class="btn btn-success">上架商品</button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href = '?action=personal_center'">取消</button>
        </div>
    </form>
    <?php
    page_footer();
}

function handle_add_to_shop() {
    check_login();
    check_shop_permission();

    $item_id = $_GET['item_id'] ?? '';
    if (!$item_id) {
        show_message('无效请求：未指定物品', 'error');
        page_header('上架物品失败');
        echo '<a href="?action=personal_center" class="btn">返回个人中心</a>';
        page_footer();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=add_to_shop&item_id=' . $item_id);
        exit;
    }

    $display_name = $_POST['display_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? 'other';
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);

    $image = '';
    $gamename = $_SESSION['user_id'];
    $uploaded = upload_image_for_user($gamename, 'image', 'Good');
    if ($uploaded) {
        $image = $uploaded;
    }

    if (!$display_name || !$description || $price <= 0 || $stock <= 0) {
        show_message('请填写完整信息', 'error');
        page_header('上架物品失败');
        echo '<a href="?action=add_to_shop&item_id=' . $item_id . '" class="btn">返回</a>';
        page_footer();
        exit;
    }

    if ($stock == 0) {
        show_message('库存不能为0', 'error');
        page_header('上架物品失败');
        echo '<a href="?action=add_to_shop&item_id=' . $item_id . '" class="btn">返回</a>';
        page_footer();
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $user = get_logged_in_user();
    $inventory = $user['inventory'] ?? [];

    if (!isset($inventory[$item_id])) {
        show_message('物品不存在', 'error');
        page_header('上架物品失败');
        echo '<a href="?action=personal_center" class="btn">返回个人中心</a>';
        page_footer();
        exit;
    }

    if ($inventory[$item_id]['quantity'] < $stock) {
        show_message('物品数量不足，当前拥有: ' . $inventory[$item_id]['quantity'], 'error');
        page_header('上架物品失败');
        echo '<a href="?action=add_to_shop&item_id=' . $item_id . '" class="btn">返回</a>';
        page_footer();
        exit;
    }

    $shops = get_all_shops();
    if (!isset($shops[$user_id])) {
        show_message('店铺不存在', 'error');
        page_header('上架物品失败');
        echo '<a href="?action=personal_center" class="btn">返回个人中心</a>';
        page_footer();
        exit;
    }

    $product_id = uniqid();
    $product = [
        'item_id' => $item_id,
        'name' => $inventory[$item_id]['name'],
        'display_name' => $display_name,
        'description' => $description,
        'category' => $category,
        'price' => $price,
        'stock' => $stock,
        'image' => $image,
        'seller_id' => $user_id,
        'status' => 'on_sale',
        'created_at' => date('Y-m-d H:i:s'),
        'nbt' => $inventory[$item_id]['nbt'] ?? ''
    ];

    save_product($user_id, $product_id, $product);

    $inventory[$item_id]['quantity'] -= $stock;
    if ($inventory[$item_id]['quantity'] <= 0) {
        delete_inventory_item($user_id, $item_id);
    } else {
        save_inventory_item($user_id, $item_id, $inventory[$item_id]);
    }

    show_message('商品上架成功！', 'success');
    page_header('商品上架成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>商品上架成功！</h2>
        <p>商品名称: <?php echo htmlspecialchars($display_name); ?></p>
        <p>上架数量: <?php echo $stock; ?></p>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=my_shop'">返回我的店铺</button>
            <button class="btn" onclick="window.location.href = '?action=personal_center'">返回个人中心</button>
        </div>
    </div>
    <?php
    page_footer();
}

function show_transactions() {
    check_login();

    $transactions = get_user_transactions($_SESSION['user_id']);

    uasort($transactions, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    page_header('全部交易记录');
    ?>
    <h1>全部交易记录</h1>

    <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <div class="icon"><i class="fas fa-clipboard-list"></i></div>
            <p>暂无交易记录</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>商品名称</th>
                    <th>商品价格</th>
                    <th>手续费</th>
                    <th>总计</th>
                    <th>时间</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $id => $transaction): ?>
                    <tr>
                        <td>
                            <?php
                            if (isset($transaction['items'])) {
                                $item_names = [];
                                foreach ($transaction['items'] as $item) {
                                    $item_names[] = $item['product_name'] . ' × ' . $item['quantity'];
                                }
                                echo htmlspecialchars(implode(', ', $item_names));
                                echo '<div style="color: #888; font-size: 0.8em; margin-top: 3px;">';
                                foreach ($transaction['items'] as $item) {
                                    echo '中文名称: ' . htmlspecialchars(get_item_chinese_name($item['product_name'])) . ' | 原始名称: ' . htmlspecialchars($item['product_name']) . '<br>';
                                }
                                echo '</div>';
                            } else {
                                echo htmlspecialchars($transaction['product_name']);
                                echo '<div style="color: #888; font-size: 0.8em; margin-top: 3px;">中文名称: ' . htmlspecialchars(get_item_chinese_name($transaction['product_name'])) . ' | 原始名称: ' . htmlspecialchars($transaction['product_name']) . '</div>';
                            }
                            ?>
                        </td>
                        <td>$<?php echo number_format($transaction['total_price'] ?? $transaction['price'], 2); ?></td>
                        <td>$<?php echo number_format($transaction['fee'], 2); ?></td>
                        <td>$<?php echo number_format($transaction['final_price'] ?? $transaction['total'], 2); ?></td>
                        <td><?php echo $transaction['created_at']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <button class="btn" onclick="window.location.href = '?action=personal_center'">返回个人中心</button>
    </div>
    <?php
    page_footer();
}

function open_shop() {
    check_login();

    $user = get_logged_in_user();

    if ($user['has_shop']) {
        show_message('您已开通店铺', 'error');
        page_header('开通店铺');
        echo '<a href="?action=personal_center" class="btn">返回个人中心</a>';
        page_footer();
        exit;
    }

    $settings = get_global_settings();
    if (!$settings['shop_enabled']) {
        show_message('店铺功能暂未开放', 'error');
        page_header('开通店铺');
        echo '<a href="?action=personal_center" class="btn">返回个人中心</a>';
        page_footer();
        exit;
    }

    $opening_fee = $settings['shop_opening_fee'];

    set_user_shop($_SESSION['user_id'], true);

    $shop_data = [
        'name' => $user['gamename'] . '的店铺',
        'description' => '这是一个新店铺',
        'owner_id' => $_SESSION['user_id'],
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'active',
        'rating' => 0,
        'rating_count' => 0
    ];

    save_shop($_SESSION['user_id'], $shop_data);

    show_message('店铺开通成功！开店费 $' . number_format($opening_fee, 2) . '欢迎入驻', 'success');
    page_header('店铺开通成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>店铺开通成功！</h2>
        <p>您的店铺已创建，可以开始上架商品了</p>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=my_shop'">进入我的店铺</button>
            <button class="btn" onclick="window.location.href = '?action=personal_center'">返回个人中心</button>
        </div>
    </div>
    <?php
    page_footer();
}

function show_my_shop() {
    check_login();
    check_shop_permission();

    $user_id = $_SESSION['user_id'];
    $user = get_logged_in_user();

    $shops = get_all_shops();
    $shop = $shops[$user_id] ?? null;

    if (!$shop) {
        show_message('店铺不存在', 'error');
        page_header('我的店铺');
        echo '<a href="?action=personal_center" class="btn">返回个人中心</a>';
        page_footer();
        exit;
    }

    $products = get_user_products($user_id);

    $categories = get_global_categories();

    $total_products = 0;
    $on_sale_products = 0;
    $total_sales = 0;
    $total_earnings = 0;

    foreach ($products as $id => $product) {
        if ($product['stock'] > 0) {
            $total_products++;
            if ($product['status'] === 'on_sale') {
                $on_sale_products++;
            }
        }
    }

    $all_transactions = get_all_transactions();
    foreach ($all_transactions as $transaction) {
        if (isset($transaction['items'])) {
            foreach ($transaction['items'] as $item) {
                if (($item['seller_id'] ?? null) === $user_id) {
                    $total_sales++;
                    $total_earnings += $item['price'] * $item['quantity'];
                }
            }
        } elseif (($transaction['seller_id'] ?? null) === $user_id) {
            $total_sales++;
            $total_earnings += $transaction['price'] ?? 0;
        }
    }

    page_header('我的店铺 - ' . htmlspecialchars($shop['name']));
    ?>
    <h1>我的店铺</h1>

    <div class="shop-header">
        <h2><?php echo htmlspecialchars($shop['name']); ?></h2>
        <p><?php echo htmlspecialchars($shop['description']); ?></p>
        <p style="margin-top: 10px;">创建时间: <?php echo $shop['created_at']; ?></p>
    </div>

    <div class="shop-stats">
        <div class="stat-card">
            <div class="value"><?php echo $total_products; ?></div>
            <div class="label">总商品数</div>
        </div>
        <div class="stat-card">
            <div class="value"><?php echo $on_sale_products; ?></div>
            <div class="label">在售商品</div>
        </div>
        <div class="stat-card">
            <div class="value"><?php echo $total_sales; ?></div>
            <div class="label">总销量</div>
        </div>
        <div class="stat-card">
            <div class="value">$<?php echo number_format($total_earnings, 2); ?></div>
            <div class="label">总收益</div>
        </div>
    </div>

    <div style="margin-bottom: 20px;">
        <button class="btn btn-success" onclick="window.location.href = '?action=add_product'">上架新商品</button>
        <button class="btn" onclick="window.location.href = '?action=edit_shop'">编辑店铺信息</button>
    </div>

    <h2>店铺商品</h2>
    <?php if (empty($products)): ?>
        <div class="empty-state">
            <div class="icon"><i class="fas fa-box"></i></div>
            <p>暂无商品</p>
            <button class="btn btn-success" onclick="window.location.href = '?action=add_product'">上架新商品</button>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>商品名称</th>
                    <th>分类</th>
                    <th>价格</th>
                    <th>库存</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $id => $product): ?>
                    <?php if ($product['stock'] > 0): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($product['display_name'] ?? get_item_chinese_name($product['name'])); ?>
                                <div style="color: #888; font-size: 0.8em; margin-top: 3px;">原始名称: <?php echo htmlspecialchars($product['name']); ?></div>
                            </td>
                            <td><span class="status-badge active"><?php echo htmlspecialchars($categories[$product['category'] ?? 'other'] ?? '其他'); ?></span></td>
                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                            <td><?php echo $product['stock']; ?></td>
                            <td><span class="status-badge <?php echo $product['status'] === 'on_sale' ? 'on-sale' : 'off-sale'; ?>"><?php echo $product['status'] === 'on_sale' ? '在售' : '下架'; ?></span></td>
                            <td>
                                <button class="btn" onclick="window.location.href = '?action=edit_product&id=<?php echo $id; ?>'">编辑</button>
                                <?php if ($product['status'] === 'on_sale'): ?>
                                    <button class="btn btn-danger" onclick="window.location.href = '?action=toggle_product&id=<?php echo $id; ?>'">下架</button>
                                <?php else: ?>
                                    <button class="btn btn-success" onclick="window.location.href = '?action=toggle_product&id=<?php echo $id; ?>'">上架</button>
                                <?php endif; ?>
                                <button class="btn btn-warning" onclick="if(confirm('确定取回吗？')) window.location.href = '?action=take_back_product&id=<?php echo $id; ?>'">取回</button>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="margin-top: 20px;">
        <button class="btn" onclick="window.location.href = '?action=personal_center'">返回个人中心</button>
    </div>
    <?php
    page_footer();
}

function show_add_product() {
    check_login();
    check_shop_permission();

    $user = get_logged_in_user();
    $inventory = $user['inventory'] ?? [];
    $categories = get_global_categories();

    page_header('上架新商品');
    ?>
    <h1>上架新商品</h1>

    <?php if (empty($inventory)): ?>
        <div class="empty-state">
            <div class="icon"><i class="fas fa-box"></i></div>
            <p>物品栏为空，请先添加物品到物品栏</p>
            <button class="btn btn-success" onclick="window.location.href = '?action=personal_center'">返回个人中心</button>
        </div>
    <?php else: ?>
        <form method="POST" action="?action=add_product" enctype="multipart/form-data">
            <div class="form-group">
                <label>选择物品</label>
                <select name="item_id" required>
                    <option value="">请选择物品</option>
                    <?php foreach ($inventory as $item_id => $item): ?>
                        <option value="<?php echo $item_id; ?>"><?php echo htmlspecialchars(get_item_chinese_name($item['name'])); ?> (原始名称: <?php echo htmlspecialchars($item['name']); ?>, 库存: <?php echo $item['quantity']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>商品显示名称（可修改）</label>
                <input type="text" name="display_name" required>
            </div>
            <div class="form-group">
                <label>商品描述</label>
                <textarea name="description" rows="4" required></textarea>
            </div>
            <div class="form-group">
                <label>商品分类</label>
                <select name="category" required>
                    <?php foreach ($categories as $cat_id => $cat_name): ?>
                        <?php if ($cat_id !== 'all'): ?>
                            <option value="<?php echo $cat_id; ?>"><?php echo htmlspecialchars($cat_name); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>价格</label>
                <input type="number" name="price" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>库存</label>
                <input type="number" name="stock" min="0" required>
            </div>
            <div class="form-group">
                <label>图片上传（可选）</label>
                <div class="file-input-wrapper">
                    <input type="file" name="image" accept="image/*">
                    <div class="file-input-custom">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>选择图片文件</span>
                    </div>
                    <small>支持 JPG、PNG 格式，最大 5MB</small>
                    <div class="file-name" style="display: none;"></div>
                </div>
            </div>
            <div class="btn-group">
                <button type="submit" class="btn btn-success">上架商品</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.href = '?action=my_shop'">取消</button>
            </div>
        </form>
    <?php endif; ?>
    <?php
    page_footer();
}

function handle_add_product() {
    check_login();
    check_shop_permission();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=add_product');
        exit;
    }

    $item_id = $_POST['item_id'] ?? '';
    $display_name = $_POST['display_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? 'other';
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);

    $image = '';
    $gamename = $_SESSION['user_id'];
    $uploaded = upload_image_for_user($gamename, 'image', 'Good');
    if ($uploaded) {
        $image = $uploaded;
    }

    if (!$item_id || !$display_name || !$description || $price <= 0 || $stock < 0) {
        show_message('请填写完整信息', 'error');
        page_header('添加商品失败');
        echo '<a href="?action=add_product" class="btn">返回</a>';
        page_footer();
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $user = get_logged_in_user();
    $inventory = $user['inventory'] ?? [];

    if (!isset($inventory[$item_id])) {
        show_message('物品不存在', 'error');
        page_header('添加商品失败');
        echo '<a href="?action=add_product" class="btn">返回</a>';
        page_footer();
        exit;
    }

    if ($inventory[$item_id]['quantity'] < $stock) {
        show_message('物品数量不足，当前库存: ' . $inventory[$item_id]['quantity'], 'error');
        page_header('添加商品失败');
        echo '<a href="?action=add_product" class="btn">返回</a>';
        page_footer();
        exit;
    }

    $shops = get_all_shops();
    if (!isset($shops[$user_id])) {
        show_message('店铺不存在', 'error');
        page_header('添加商品失败');
        echo '<a href="?action=my_shop" class="btn">返回我的店铺</a>';
        page_footer();
        exit;
    }

    $product_id = uniqid();
    $product = [
        'item_id' => $item_id,
        'name' => $inventory[$item_id]['name'],
        'display_name' => $display_name,
        'description' => $description,
        'category' => $category,
        'price' => $price,
        'stock' => $stock,
        'image' => $image,
        'seller_id' => $user_id,
        'status' => 'on_sale',
        'created_at' => date('Y-m-d H:i:s'),
        'nbt' => $inventory[$item_id]['nbt'] ?? ''
    ];

    save_product($user_id, $product_id, $product);

    $inventory[$item_id]['quantity'] -= $stock;
    if ($inventory[$item_id]['quantity'] <= 0) {
        delete_inventory_item($user_id, $item_id);
    } else {
        save_inventory_item($user_id, $item_id, $inventory[$item_id]);
    }

    show_message('商品上架成功！', 'success');
    page_header('商品上架成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>商品上架成功！</h2>
        <p>商品名称: <?php echo htmlspecialchars($display_name); ?></p>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=my_shop'">返回我的店铺</button>
            <button class="btn" onclick="window.location.href = '?action=add_product'">继续上架商品</button>
        </div>
    </div>
    <?php
    page_footer();
}

function show_edit_product() {
    check_login();
    check_shop_permission();

    $product_id = $_GET['id'] ?? '';
    if (!$product_id) {
        show_message('无效请求：未指定商品', 'error');
        page_header('编辑商品');
        echo '<a href="?action=my_shop" class="btn">返回我的店铺</a>';
        page_footer();
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $products = get_user_products($user_id);
    $product = $products[$product_id] ?? null;

    if (!$product) {
        show_message('商品不存在或无权限', 'error');
        page_header('编辑商品');
        echo '<a href="?action=my_shop" class="btn">返回我的店铺</a>';
        page_footer();
        exit;
    }

    $categories = get_global_categories();

    page_header('编辑商品 - ' . htmlspecialchars($product['name']));
    ?>
    <h1>编辑商品</h1>

    <form method="POST" action="?action=edit_product&id=<?php echo $product_id; ?>" enctype="multipart/form-data">
        <div class="form-group">
            <label>商品显示名称（可修改）</label>
            <input type="text" name="display_name" value="<?php echo htmlspecialchars($product['display_name'] ?? $product['name']); ?>" required>
            <small style="color: #666;">原始名称: <?php echo htmlspecialchars($product['name']); ?>（不可修改）</small>
        </div>
        <div class="form-group">
            <label>商品描述</label>
            <textarea name="description" rows="4" required><?php echo htmlspecialchars($product['description']); ?></textarea>
        </div>
        <div class="form-group">
            <label>商品分类</label>
            <select name="category" required>
                <?php foreach ($categories as $cat_id => $cat_name): ?>
                    <?php if ($cat_id !== 'all'): ?>
                        <option value="<?php echo $cat_id; ?>" <?php echo ($product['category'] ?? 'other') === $cat_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat_name); ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>价格</label>
            <input type="number" name="price" step="0.01" min="0" value="<?php echo $product['price']; ?>" required>
        </div>
        <div class="form-group">
            <label>库存</label>
            <input type="number" name="stock" min="0" value="<?php echo $product['stock']; ?>" required>
        </div>
        <div class="form-group">
            <label>图片上传（可选）</label>
            <div class="file-input-wrapper">
                <input type="file" name="image" accept="image/*">
                <div class="file-input-custom">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>选择图片文件</span>
                </div>
                <small>支持 JPG、PNG 格式，最大 5MB</small>
                <div class="file-name" style="display: none;"></div>
            </div>
            <?php if (!empty($product['image'])): ?>
                <small style="color: #666;">当前图片: <?php echo htmlspecialchars($product['image']); ?></small>
            <?php endif; ?>
        </div>
        <div class="btn-group">
            <button type="submit" class="btn btn-success">保存修改</button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href = '?action=my_shop'">取消</button>
        </div>
    </form>
    <?php
    page_footer();
}

function handle_edit_product() {
    check_login();
    check_shop_permission();

    $product_id = $_GET['id'] ?? '';
    if (!$product_id) {
        show_message('无效请求：未指定商品', 'error');
        page_header('编辑商品');
        echo '<a href="?action=my_shop" class="btn">返回我的店铺</a>';
        page_footer();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        show_message('无效请求：请求方式错误', 'error');
        page_header('编辑商品');
        echo '<a href="?action=edit_product&id=' . $product_id . '" class="btn">返回</a>';
        page_footer();
        exit;
    }

    $display_name = $_POST['display_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? 'other';
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);

    $user_id = $_SESSION['user_id'];

    $image = '';
    $products = get_user_products($user_id);
    $product = $products[$product_id] ?? null;

    if ($product && isset($product['image'])) {
        $image = $product['image'];
    }

    $gamename = $user_id;
    $uploaded = upload_image_for_user($gamename, 'image', 'Good');
    if ($uploaded) {
        $image = $uploaded;
    }

    if (!$display_name || !$description || $price <= 0 || $stock < 0) {
        show_message('请填写完整信息', 'error');
        page_header('编辑商品失败');
        echo '<a href="?action=edit_product&id=' . $product_id . '" class="btn">返回</a>';
        page_footer();
        exit;
    }

    if (!$product) {
        show_message('商品不存在或无权限', 'error');
        page_header('编辑商品失败');
        echo '<a href="?action=my_shop" class="btn">返回我的店铺</a>';
        page_footer();
        exit;
    }

    $original_stock = $product['stock'];
    $item_id = $product['item_id'];

    $inventory = get_user_inventory($user_id);

    $total_stock_in_shop = 0;
    foreach ($products as $id => $p) {
        if ($p['item_id'] === $item_id && $id !== $product_id) {
            $total_stock_in_shop += $p['stock'];
        }
    }

    $inventory_quantity = isset($inventory[$item_id]) ? $inventory[$item_id]['quantity'] : 0;

    $max_stock = $inventory_quantity + $original_stock - $total_stock_in_shop;

    if ($stock > $max_stock) {
        show_message('库存不能超过可用数量（当前可用: ' . $max_stock . '）', 'error');
        page_header('编辑商品失败');
        echo '<a href="?action=edit_product&id=' . $product_id . '" class="btn">返回</a>';
        page_footer();
        exit;
    }

    $diff = $original_stock - $stock;

    if ($diff != 0) {
        if (!isset($inventory[$item_id])) {
            $inventory[$item_id] = [
                'name' => $product['name'],
                'quantity' => 0,
                'nbt' => $product['nbt'] ?? ''
            ];
        }

        $inventory[$item_id]['quantity'] += $diff;

        if ($inventory[$item_id]['quantity'] <= 0) {
            unset($inventory[$item_id]);
            delete_inventory_item($user_id, $item_id);
        } else {
            save_inventory_item($user_id, $item_id, $inventory[$item_id]);
        }
    }

    $product['display_name'] = $display_name;
    $product['description'] = $description;
    $product['category'] = $category;
    $product['price'] = $price;
    $product['stock'] = $stock;
    $product['image'] = $image;

    save_product($user_id, $product_id, $product);

    show_message('商品修改成功！', 'success');
    page_header('商品修改成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>商品修改成功！</h2>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=my_shop'">返回我的店铺</button>
        </div>
    </div>
    <?php
    page_footer();
}

function toggle_product() {
    check_login();
    check_shop_permission();

    $product_id = $_GET['id'] ?? '';
    if (!$product_id) {
        header('Location: ?action=my_shop');
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $products = get_user_products($user_id);

    if (!isset($products[$product_id]) || $products[$product_id]['seller_id'] !== $user_id) {
        show_message('商品不存在或无权限', 'error');
        page_header('操作失败');
        echo '<a href="?action=my_shop" class="btn">返回我的店铺</a>';
        page_footer();
        exit;
    }

    $product = $products[$product_id];
    $item_id = $product['item_id'] ?? null;
    $stock = $product['stock'] ?? 0;

    if ($stock <= 0) {
        if ($item_id && $stock > 0) {
            $inventory = get_user_inventory($user_id);
            if (isset($inventory[$item_id])) {
                $inventory[$item_id]['quantity'] += $stock;
                save_inventory_item($user_id, $item_id, $inventory[$item_id]);
            } else {
                $new_item = [
                    'name' => $product['name'],
                    'quantity' => $stock,
                    'nbt' => $product['nbt'] ?? ''
                ];
                save_inventory_item($user_id, $item_id, $new_item);
            }
        }

        delete_product_file($user_id, $product_id);

        show_message('商品已删除（库存为0）', 'success');
        page_header('操作成功');
        ?>
        <div style="text-align: center; padding: 30px;">
            <h2>商品已删除（库存为0）</h2>
            <div style="margin-top: 20px;">
                <button class="btn btn-success" onclick="window.location.href = '?action=my_shop'">返回我的店铺</button>
            </div>
        </div>
        <?php
        page_footer();
        exit;
    }

    $current_status = $product['status'];
    $new_status = $current_status === 'on_sale' ? 'off_sale' : 'on_sale';
    $product['status'] = $new_status;

    save_product($user_id, $product_id, $product);

    $action = $new_status === 'on_sale' ? '上架' : '下架';
    show_message("商品{$action}成功！", 'success');
    page_header('操作成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>商品<?php echo $action; ?>成功！</h2>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=my_shop'">返回我的店铺</button>
        </div>
    </div>
    <?php
    page_footer();
}

function take_back_product() {
    check_login();
    check_shop_permission();

    $product_id = $_GET['id'] ?? '';
    if (!$product_id) {
        header('Location: ?action=my_shop');
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $products = get_user_products($user_id);

    if (!isset($products[$product_id]) || $products[$product_id]['seller_id'] !== $user_id) {
        show_message('商品不存在或无权限', 'error');
        page_header('取回失败');
        echo '<a href="?action=my_shop" class="btn">返回我的店铺</a>';
        page_footer();
        exit;
    }

    $product = $products[$product_id];
    $item_id = $product['item_id'] ?? null;
    $stock = $product['stock'] ?? 0;

    delete_product_file($user_id, $product_id);

    if ($item_id && $stock > 0) {
        $inventory = get_user_inventory($user_id);
        if (isset($inventory[$item_id])) {
            $inventory[$item_id]['quantity'] += $stock;
            save_inventory_item($user_id, $item_id, $inventory[$item_id]);
        } else {
            $new_item = [
                'name' => $product['name'],
                'quantity' => $stock,
                'nbt' => $product['nbt'] ?? ''
            ];
            save_inventory_item($user_id, $item_id, $new_item);
        }
    }

    show_message('商品取回成功！', 'success');
    page_header('商品取回成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>商品取回成功！</h2>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=my_shop'">返回我的店铺</button>
        </div>
    </div>
    <?php
    page_footer();
}

function delete_product() {
    check_login();
    check_shop_permission();

    $product_id = $_GET['id'] ?? '';
    if (!$product_id) {
        header('Location: ?action=my_shop');
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $products = get_user_products($user_id);

    if (!isset($products[$product_id]) || $products[$product_id]['seller_id'] !== $user_id) {
        show_message('商品不存在或无权限', 'error');
        page_header('删除失败');
        echo '<a href="?action=my_shop" class="btn">返回我的店铺</a>';
        page_footer();
        exit;
    }

    $product = $products[$product_id];
    $item_id = $product['item_id'] ?? null;
    $stock = $product['stock'] ?? 0;

    delete_product_file($user_id, $product_id);

    if ($item_id && $stock > 0) {
        $inventory = get_user_inventory($user_id);
        if (isset($inventory[$item_id])) {
            $inventory[$item_id]['quantity'] += $stock;
            save_inventory_item($user_id, $item_id, $inventory[$item_id]);
        } else {
            $new_item = [
                'name' => $product['name'],
                'quantity' => $stock,
                'nbt' => $product['nbt'] ?? ''
            ];
            save_inventory_item($user_id, $item_id, $new_item);
        }
    }

    show_message('商品删除成功！', 'success');
    page_header('商品删除成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>商品删除成功！</h2>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=my_shop'">返回我的店铺</button>
        </div>
    </div>
    <?php
    page_footer();
}

function show_edit_shop() {
    check_login();
    check_shop_permission();

    $user = get_logged_in_user();
    $user_id = $_SESSION['user_id'];

    $all_shops = get_all_shops();
    $shop = $all_shops[$user_id] ?? null;

    if (!$shop) {
        show_message('店铺不存在', 'error');
        page_header('编辑店铺');
        echo '<a href="?action=my_shop" class="btn">返回我的店铺</a>';
        page_footer();
        exit;
    }

    page_header('编辑店铺信息');
    ?>
    <h1>编辑店铺信息</h1>

    <form method="POST" action="?action=edit_shop">
        <div class="form-group">
            <label>店铺名称</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($shop['name']); ?>" required>
        </div>
        <div class="form-group">
            <label>店铺描述</label>
            <textarea name="description" rows="4" required><?php echo htmlspecialchars($shop['description']); ?></textarea>
        </div>
        <button type="submit" class="btn btn-success">保存修改</button>
        <button type="button" class="btn" onclick="window.location.href = '?action=my_shop'">取消</button>
    </form>
    <?php
    page_footer();
}

function handle_edit_shop() {
    check_login();
    check_shop_permission();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=edit_shop');
        exit;
    }

    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';

    if (!$name || !$description) {
        show_message('请填写完整信息', 'error');
        page_header('编辑店铺失败');
        echo '<a href="?action=edit_shop" class="btn">返回</a>';
        page_footer();
        exit;
    }

    $user_id = $_SESSION['user_id'];

    $shop_file = SHOP_MEDIA_DIR . '/' . $user_id . '/shop.json';
    $shop_data = [];
    if (file_exists($shop_file)) {
        $content = file_get_contents($shop_file);
        $shop_data = json_decode($content, true) ?? [];
    }

    if (empty($shop_data)) {
        $shop_data = [
            'owner_id' => $user_id,
            'name' => $name,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
            'rating' => 0,
            'rating_count' => 0
        ];
    } else {
        $shop_data['name'] = $name;
        $shop_data['description'] = $description;
    }

    $shop_data['owner_id'] = $user_id;

    save_shop($user_id, $shop_data);

    show_message('店铺信息修改成功！', 'success');
    page_header('店铺信息修改成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>店铺信息修改成功！</h2>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=my_shop'">返回我的店铺</button>
        </div>
    </div>
    <?php
    page_footer();
}

function show_admin() {
    check_login();
    check_admin_permission();

    $settings = get_global_settings();

    $users = [];
    $user_files = glob(SHOP_DATA_DIR . '/Users/*.json');
    foreach ($user_files as $file) {
        $gamename = basename($file, '.json');
        $content = file_get_contents($file);
        $user_data = json_decode($content, true);
        if (is_array($user_data)) {
            $users[$gamename] = $user_data;
        }
    }

    $categories = get_global_categories();
    $all_products = get_all_products();
    $active_products = array_filter($all_products, function($product) {
        return ($product['stock'] ?? 0) > 0 && ($product['status'] ?? '') === 'on_sale';
    });
    $all_shops = get_all_shops();
    $all_transactions = get_all_transactions();

    page_header('管理后台');
    ?>
    <h1>管理后台</h1>

    <div class="admin-section">
        <h2>交易设置</h2>
        <form method="POST" action="?action=admin">
            <div class="form-group">
                <label>交易手续费（百分比）</label>
                <input type="number" name="transaction_fee" step="0.01" min="0" max="100" value="<?php echo $settings['transaction_fee'] * 100; ?>" required>
                <small style="color: #666;">当前: <?php echo $settings['transaction_fee'] * 100; ?>%</small>
            </div>
            <div class="form-group">
                <label>开店费用</label>
                <input type="number" name="shop_opening_fee" step="0.01" min="0" value="<?php echo $settings['shop_opening_fee']; ?>" required>
                <small style="color: #666;">当前: $<?php echo number_format($settings['shop_opening_fee'], 2); ?></small>
            </div>
            <div class="form-group">
                <label>店铺功能开关</label>
                <select name="shop_enabled">
                    <option value="1" <?php echo $settings['shop_enabled'] ? 'selected' : ''; ?>>开启</option>
                    <option value="0" <?php echo !$settings['shop_enabled'] ? 'selected' : ''; ?>>关闭</option>
                </select>
            </div>
            <div class="form-group">
                <label>语言文件</label>
                <select name="lang_file">
                    <option value="">不使用语言文件</option>
                    <?php
                    $lang_files = get_available_lang_files();
                    foreach ($lang_files as $file):
                    ?>
                        <option value="<?php echo htmlspecialchars($file); ?>" <?php echo $settings['lang_file'] === $file ? 'selected' : ''; ?>><?php echo htmlspecialchars($file); ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #666;">选择用于物品名称映射的语言文件（位于 lang/ 目录）</small>
            </div>
            <div class="form-group">
                <label>是否允许在网页注册</label>
                <select name="allow_web_registration">
                    <option value="1" <?php echo ($settings['allow_web_registration'] ?? true) ? 'selected' : ''; ?>>允许</option>
                    <option value="0" <?php echo !($settings['allow_web_registration'] ?? true) ? 'selected' : ''; ?>>禁止</option>
                </select>
                <small style="color: #666;">如果禁止，用户只能通过修改密码API创建账户</small>
            </div>
            <div class="form-group">
                <label>锚定物ID</label>
                <input type="text" name="anchor_item" value="<?php echo htmlspecialchars($settings['anchor_item'] ?? 'minecraft:iron_ingot'); ?>" required>
                <small style="color: #666;">用于充值提现的物品ID，如 minecraft:iron_ingot（用户将看到映射名：<?php echo htmlspecialchars(get_item_chinese_name($settings['anchor_item'] ?? 'minecraft:iron_ingot')); ?>）</small>
            </div>
            <div class="form-group">
                <label>汇率（1个锚定物兑换多少余额）</label>
                <input type="number" name="exchange_rate" step="0.01" min="0.01" value="<?php echo htmlspecialchars($settings['exchange_rate'] ?? 1.0); ?>" required>
                <small style="color: #666;">当前: 1个<?php echo htmlspecialchars(get_item_chinese_name($settings['anchor_item'] ?? 'minecraft:iron_ingot')); ?> = <?php echo $settings['exchange_rate'] ?? 1.0; ?> 余额</small>
            </div>
            <div class="form-group">
                <label>API Token（用于API请求验证）</label>
                <input type="text" name="api_token" value="<?php echo htmlspecialchars($settings['api_token'] ?? '1145141919810'); ?>" required>
                <small style="color: #666;">用于验证 API 请求的令牌，请妥善保管。当前 token 为 <code><?php echo htmlspecialchars($settings['api_token'] ?? '1145141919810'); ?></code></small>
            </div>
            <button type="submit" class="btn btn-success">保存设置</button>
        </form>
    </div>

    <div class="admin-section">
        <h2>用户管理</h2>
        <table>
            <thead>
                <tr>
                    <th>用户名</th>
                    <th>店铺状态</th>
                    <th>管理员</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $gamename => $user_data): ?>
                    <?php
                    $gamename = $user_data['gamename'] ?? ($user_data['gamename'] ?? '未知');
                    $has_shop = has_user_shop($gamename);
                    $is_admin = is_shop_admin($gamename);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($gamename); ?></td>
                        <td><?php echo $has_shop ? '已开通' : '未开通'; ?></td>
                        <td><?php echo $is_admin ? '是' : '否'; ?></td>
                        <td>
                            <?php if (!$is_admin && $gamename !== $_SESSION['user_id']): ?>
                                <button class="btn" onclick="window.location.href = '?action=toggle_admin&id=<?php echo $gamename; ?>'">设为管理员</button>
                            <?php endif; ?>
                            <?php if ($has_shop && $gamename !== $_SESSION['user_id']): ?>
                                <button class="btn btn-danger" onclick="if(confirm('确定关闭该用户的店铺吗？')) window.location.href = '?action=close_shop&id=<?php echo $gamename; ?>'">关闭店铺</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="admin-section">
        <h2>分类管理</h2>
        <table>
            <thead>
                <tr>
                    <th>分类ID</th>
                    <th>分类名称</th>
                    <th>商品数量</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat_id => $cat_name): ?>
                    <?php if ($cat_id !== 'all'): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cat_id); ?></td>
                            <td><?php echo htmlspecialchars($cat_name); ?></td>
                            <td>
                                <?php
                                $count = 0;
                                foreach ($active_products as $product) {
                                    if (($product['category'] ?? 'other') === $cat_id) {
                                        $count++;
                                    }
                                }
                                echo $count;
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="admin-section">
        <h2>系统统计</h2>
        <div class="stats-grid">
            <div class="stat-card-modern">
                <div class="value"><?php echo count($users); ?></div>
                <div class="label">总用户数</div>
            </div>
            <div class="stat-card-modern">
                <div class="value"><?php echo count($active_products); ?></div>
                <div class="label">活跃商品数</div>
            </div>
            <div class="stat-card-modern">
                <div class="value"><?php echo count($all_shops); ?></div>
                <div class="label">总店铺数</div>
            </div>
            <div class="stat-card-modern">
                <div class="value"><?php echo count($all_transactions); ?></div>
                <div class="label">总交易数</div>
            </div>
        </div>
    </div>

    <div style="margin-top: 20px;">
        <button class="btn" onclick="window.location.href = '?action=personal_center'">返回个人中心</button>
    </div>
    <?php
    page_footer();
}

function handle_admin_settings() {
    check_login();
    check_admin_permission();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=admin');
        exit;
    }

    $transaction_fee = floatval($_POST['transaction_fee'] ?? 0) / 100;
    $shop_opening_fee = floatval($_POST['shop_opening_fee'] ?? 0);
    $shop_enabled = isset($_POST['shop_enabled']) && $_POST['shop_enabled'] === '1';
    $lang_file = $_POST['lang_file'] ?? '';
    $anchor_item = trim($_POST['anchor_item'] ?? '');
    $exchange_rate = floatval($_POST['exchange_rate'] ?? 1.0);
    $api_token = trim($_POST['api_token'] ?? '');
    $allow_web_registration = isset($_POST['allow_web_registration']) && $_POST['allow_web_registration'] === '1';

    if ($transaction_fee < 0 || $transaction_fee > 1) {
        show_message('手续费必须在0-100之间', 'error');
        page_header('设置失败');
        echo '<a href="?action=admin" class="btn">返回管理后台</a>';
        page_footer();
        exit;
    }

    if ($shop_opening_fee < 0) {
        show_message('开店费用不能为负数', 'error');
        page_header('设置失败');
        echo '<a href="?action=admin" class="btn">返回管理后台</a>';
        page_footer();
        exit;
    }

    if (empty($anchor_item)) {
        show_message('锚定物ID不能为空', 'error');
        page_header('设置失败');
        echo '<a href="?action=admin" class="btn">返回管理后台</a>';
        page_footer();
        exit;
    }

    if ($exchange_rate <= 0) {
        show_message('汇率必须大于0', 'error');
        page_header('设置失败');
        echo '<a href="?action=admin" class="btn">返回管理后台</a>';
        page_footer();
        exit;
    }

    if ($lang_file !== '') {
        $lang_path = __DIR__ . '/lang/' . $lang_file;
        if (!file_exists($lang_path)) {
            show_message('所选语言文件不存在：' . htmlspecialchars($lang_file), 'error');
            page_header('设置失败');
            echo '<a href="?action=admin" class="btn">返回管理后台</a>';
            page_footer();
            exit;
        }
    }

    if (empty($api_token)) {
        show_message('API Token 不能为空', 'error');
        page_header('设置失败');
        echo '<a href="?action=admin" class="btn">返回管理后台</a>';
        page_footer();
        exit;
    }
    if (strlen($api_token) < 6) {
        show_message('API Token 长度至少6位', 'error');
        page_header('设置失败');
        echo '<a href="?action=admin" class="btn">返回管理后台</a>';
        page_footer();
        exit;
    }

    $settings = [
        'transaction_fee' => $transaction_fee,
        'shop_opening_fee' => $shop_opening_fee,
        'shop_enabled' => $shop_enabled,
        'lang_file' => $lang_file,
        'allow_web_registration' => $allow_web_registration,
        'anchor_item' => $anchor_item,
        'exchange_rate' => $exchange_rate,
        'api_token' => $api_token
    ];
    save_global_settings($settings);

    show_message('设置保存成功！', 'success');
    page_header('设置保存成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>设置保存成功！</h2>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=admin'">返回管理后台</button>
        </div>
    </div>
    <?php
    page_footer();
}

function toggle_admin() {
    check_login();
    check_admin_permission();

    $user_id = $_GET['id'] ?? '';
    if (!$user_id) {
        header('Location: ?action=admin');
        exit;
    }

    $user_data = get_shop_user($user_id);
    if (!$user_data) {
        show_message('用户不存在', 'error');
        page_header('操作失败');
        echo '<a href="?action=admin" class="btn">返回管理后台</a>';
        page_footer();
        exit;
    }

    if ($user_id === $_SESSION['user_id']) {
        show_message('不能修改自己的管理员状态', 'error');
        page_header('操作失败');
        echo '<a href="?action=admin" class="btn">返回管理后台</a>';
        page_footer();
        exit;
    }

    $current_role = $user_data['role'] ?? '';
    $new_role = ($current_role === 'admin') ? '' : 'admin';
    save_shop_user($user_id, ['role' => $new_role]);

    $action = $new_role === 'admin' ? '设为' : '取消';
    show_message("用户{$action}管理员成功！", 'success');
    page_header('操作成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>用户<?php echo $action; ?>管理员成功！</h2>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=admin'">返回管理后台</button>
        </div>
    </div>
    <?php
    page_footer();
}

function close_shop() {
    check_login();
    check_admin_permission();

    $user_id = $_GET['id'] ?? '';
    if (!$user_id) {
        header('Location: ?action=admin');
        exit;
    }

    $user_data = get_shop_user($user_id);
    if (!$user_data) {
        show_message('用户不存在', 'error');
        page_header('操作失败');
        echo '<a href="?action=admin" class="btn">返回管理后台</a>';
        page_footer();
        exit;
    }

    if (!has_user_shop($user_id)) {
        show_message('该用户没有店铺', 'error');
        page_header('操作失败');
        echo '<a href="?action=admin" class="btn">返回管理后台</a>';
        page_footer();
        exit;
    }

    set_user_shop($user_id, false);

    delete_shop($user_id);

    $products = get_user_products($user_id);
    foreach ($products as $product_id => $product) {
        $product['status'] = 'off_sale';
        save_product($user_id, $product_id, $product);
    }

    show_message('店铺关闭成功！', 'success');
    page_header('店铺关闭成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>店铺关闭成功！</h2>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=admin'">返回管理后台</button>
        </div>
    </div>
    <?php
    page_footer();
}

function show_cart() {
    check_login();
    page_header('购物车');

    $user_id = $_SESSION['user_id'];
    $cart_items = get_user_cart($user_id);
    $all_products = get_all_products();
    $all_shops = get_all_shops();
    $total_price = 0;

    $new_cart = [];
    $changed = false;
    foreach ($cart_items as $item_id => $item) {
        $product = $all_products[$item['product_id']] ?? null;
        if ($product && $product['status'] === 'on_sale' && $product['stock'] > 0) {
            $new_cart[$item_id] = $item;
        } else {
            $changed = true;
        }
    }
    if ($changed) {
        save_user_cart($user_id, $new_cart);
        $cart_items = $new_cart;
    }

    foreach ($cart_items as $item_id => $item) {
        $product = $all_products[$item['product_id']] ?? null;
        if ($product && $product['status'] === 'on_sale') {
            $total_price += $product['price'] * $item['quantity'];
        }
    }

    ?>
    <h1>购物车</h1>

    <?php if (empty($cart_items)): ?>
        <div class="empty-state">
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            <p>购物车是空的</p>
            <button class="btn btn-success" onclick="window.location.href = '?action=shop'">去购物</button>
        </div>
    <?php else: ?>
        <div class="cart-items">
            <?php foreach ($cart_items as $item_id => $item): ?>
                <?php
                $product = $all_products[$item['product_id']] ?? null;
                if (!$product || $product['status'] !== 'on_sale') {
                    continue;
                }
                ?>
                <div class="cart-item">
                    <div class="item-image">
                        <?php if (!empty($product['image'])): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars(get_item_chinese_name($product['name'])); ?>">
                        <?php else: ?>
                            <i class="fas fa-image"></i>
                        <?php endif; ?>
                    </div>
                    <div class="item-info">
                        <div class="item-name" style="word-wrap: break-word; overflow-wrap: break-word; max-width: 100%;"><?php echo htmlspecialchars(get_item_chinese_name($product['name'])); ?></div>
                        <div style="color: #888; font-size: 0.8em; margin-top: 3px;">原始名称: <?php echo htmlspecialchars($product['name']); ?></div>
                        <div class="item-price">$<?php echo number_format($product['price'], 2); ?></div>
                        <?php $shop_gamename = $product['shop_id'] ?? ($product['seller_id'] ?? ($product['seller_gamename'] ?? '')); ?>
                        <div class="shop-name">店铺: <?php echo htmlspecialchars($all_shops[$shop_gamename]['name'] ?? '未知店铺'); ?></div>
                        <?php if (!empty($product['nbt'])): ?>
                            <div class="item-attributes"><?php echo htmlspecialchars(parse_nbt_to_attributes($product['nbt'], $product['name'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="item-actions">
                        <div class="quantity-control">
                            <button onclick="updateQuantity('<?php echo $item_id; ?>', -1)">-</button>
                            <span><?php echo $item['quantity']; ?></span>
                            <button onclick="updateQuantity('<?php echo $item_id; ?>', 1)">+</button>
                        </div>
                        <button class="btn btn-danger" onclick="removeFromCart('<?php echo $item_id; ?>')">删除</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-summary">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>订单总计</h3>
                <div class="price-tag">$<?php echo number_format($total_price, 2); ?></div>
            </div>
            <div class="btn-group">
                <button class="btn btn-success" onclick="checkout()">结算</button>
                <button class="btn btn-secondary" onclick="window.location.href = '?action=shop'">继续购物</button>
                <button class="btn btn-danger" onclick="clearCart()">清空购物车</button>
            </div>
        </div>
    <?php endif; ?>

    <script>
    function updateQuantity(itemId, change) {
        fetch('?action=update_cart_quantity&id=' + itemId + '&change=' + change)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    showNotification(data.error || '更新失败', 'error');
                }
            })
            .catch(error => {
                showNotification('网络错误，请重试', 'error');
            });
    }

    function removeFromCart(itemId) {
        if (!confirm('确定要删除这个商品吗？')) {
            return;
        }
        fetch('?action=remove_from_cart&id=' + itemId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    showNotification(data.error || '删除失败', 'error');
                }
            })
            .catch(error => {
                showNotification('网络错误，请重试', 'error');
            });
    }

    function clearCart() {
        if (!confirm('确定要清空购物车吗？')) {
            return;
        }
        fetch('?action=clear_cart')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    showNotification(data.error || '清空失败', 'error');
                }
            })
            .catch(error => {
                showNotification('网络错误，请重试', 'error');
            });
    }

    function checkout() {
        if (!confirm('确定要结算吗？')) {
            return;
        }
        fetch('?action=checkout')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('结算成功！共 ' + data.item_count + ' 件商品', 'success');
                    setTimeout(() => {
                        window.location.href = '?action=personal_center';
                    }, 1500);
                } else {
                    showNotification(data.error || '结算失败', 'error');
                }
            })
            .catch(error => {
                showNotification('网络错误，请重试', 'error');
            });
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = 'alert alert-' + type;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.animation = 'slideIn 0.3s ease';
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    </script>

    <?php
    page_footer();
}

function add_review() {
    check_login();

    $shop_id = $_GET['id'] ?? '';
    if (!$shop_id) {
        header('Location: ?action=shop');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?action=shop_detail&id=' . $shop_id);
        exit;
    }

    $rating = intval($_POST['rating'] ?? 0);
    $content = $_POST['content'] ?? '';

    if ($rating < 1 || $rating > 5 || !$content) {
        show_message('请填写完整评价信息', 'error');
        page_header('评价失败');
        echo '<a href="?action=shop_detail&id=' . $shop_id . '" class="btn">返回店铺详情</a>';
        page_footer();
        exit;
    }

    $shop_file = SHOP_MEDIA_DIR . '/' . $shop_id . '/shop.json';
    if (!file_exists($shop_file)) {
        show_message('店铺不存在', 'error');
        page_header('评价失败');
        echo '<a href="?action=shop" class="btn">返回购物中心</a>';
        page_footer();
        exit;
    }

    $user = get_logged_in_user();
    $current_gamename = $user['gamename'];

    $feedbacks = get_user_feedbacks($shop_id);

    $existing_review_id = null;
    foreach ($feedbacks as $id => $fb) {
        if (($fb['gamename'] ?? '') === $current_gamename) {
            $existing_review_id = $id;
            break;
        }
    }

    if ($existing_review_id) {
        $old_file = SHOP_MEDIA_DIR . '/' . $shop_id . '/Feedback/' . $existing_review_id . '.json';
        if (file_exists($old_file)) {
            unlink($old_file);
        }
    }

    $review_id = uniqid();
    $feedback = [
        'gamename' => $current_gamename,
        'rating' => $rating,
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s')
    ];

    save_feedback($shop_id, $review_id, $feedback);

    show_message('评价成功！', 'success');
    page_header('评价成功');
    ?>
    <div style="text-align: center; padding: 30px;">
        <h2>评价成功！</h2>
        <p>感谢您的评价！</p>
        <div style="margin-top: 20px;">
            <button class="btn btn-success" onclick="window.location.href = '?action=shop_detail&id=<?php echo $shop_id; ?>'">返回店铺详情</button>
        </div>
    </div>
    <?php
    page_footer();
}

function add_to_cart() {
    check_login();

    $product_id = $_GET['id'] ?? '';
    if (!$product_id) {
        echo json_encode(['success' => false, 'error' => '商品ID缺失']);
        exit;
    }

    $all_products = get_all_products();
    $product = $all_products[$product_id] ?? null;

    if (!$product || $product['status'] !== 'on_sale') {
        echo json_encode(['success' => false, 'error' => '商品不存在或已下架']);
        exit;
    }

    if ($product['stock'] <= 0) {
        echo json_encode(['success' => false, 'error' => '商品库存不足']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $cart = get_user_cart($user_id);

    $found = false;
    foreach ($cart as $item_id => $item) {
        if ($item['product_id'] === $product_id) {
            if ($item['quantity'] + 1 > $product['stock']) {
                echo json_encode(['success' => false, 'error' => '库存不足']);
                exit;
            }
            $cart[$item_id]['quantity'] += 1;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $item_id = uniqid();
        $cart[$item_id] = [
            'product_id' => $product_id,
            'quantity' => 1
        ];
    }

    save_user_cart($user_id, $cart);

    echo json_encode(['success' => true, 'message' => '商品已加入购物车']);
    exit;
}

function get_cart_count() {
    check_login();

    $user_id = $_SESSION['user_id'];
    $cart = get_user_cart($user_id);
    $all_products = get_all_products();

    $valid_count = 0;
    foreach ($cart as $item_id => $item) {
        $product_id = $item['product_id'];
        $product = $all_products[$product_id] ?? null;
        if ($product && $product['status'] === 'on_sale' && ($product['stock'] ?? 0) > 0) {
            $valid_count++;
        }
    }

    echo json_encode(['success' => true, 'count' => $valid_count]);
    exit;
}

function update_cart_quantity() {
    check_login();

    $item_id = $_GET['id'] ?? '';
    $change = intval($_GET['change'] ?? 0);

    if (!$item_id || $change === 0) {
        echo json_encode(['success' => false, 'error' => '参数错误']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $cart = get_user_cart($user_id);

    if (!isset($cart[$item_id])) {
        echo json_encode(['success' => false, 'error' => '商品不存在']);
        exit;
    }

    $item = $cart[$item_id];
    $all_products = get_all_products();
    $product = $all_products[$item['product_id']] ?? null;

    if (!$product || $product['status'] !== 'on_sale') {
        echo json_encode(['success' => false, 'error' => '商品不存在或已下架']);
        exit;
    }

    $new_quantity = $item['quantity'] + $change;

    if ($new_quantity <= 0) {
        unset($cart[$item_id]);
    } else if ($new_quantity > $product['stock']) {
        echo json_encode(['success' => false, 'error' => '库存不足']);
        exit;
    } else {
        $cart[$item_id]['quantity'] = $new_quantity;
    }

    save_user_cart($user_id, $cart);

    echo json_encode(['success' => true, 'message' => '数量已更新']);
    exit;
}

function remove_from_cart() {
    check_login();

    $item_id = $_GET['id'] ?? '';
    if (!$item_id) {
        echo json_encode(['success' => false, 'error' => '商品ID缺失']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $cart = get_user_cart($user_id);

    if (!isset($cart[$item_id])) {
        echo json_encode(['success' => false, 'error' => '商品不存在']);
        exit;
    }

    unset($cart[$item_id]);
    save_user_cart($user_id, $cart);

    echo json_encode(['success' => true, 'message' => '商品已移除']);
    exit;
}

function clear_cart() {
    check_login();

    $user_id = $_SESSION['user_id'];
    save_user_cart($user_id, []);

    echo json_encode(['success' => true, 'message' => '购物车已清空']);
    exit;
}

function checkout() {
    check_login();

    $user_id = $_SESSION['user_id'];
    $cart = get_user_cart($user_id);

    if (empty($cart)) {
        echo json_encode(['success' => false, 'error' => '购物车为空']);
        exit;
    }

    $all_products = get_all_products();
    $settings = get_global_settings();
    $total_price = 0;
    $transaction_items = [];
    $product_updates = [];

    foreach ($cart as $item_id => $cart_item) {
        $product_id = $cart_item['product_id'];
        if (!isset($all_products[$product_id])) {
            echo json_encode(['success' => false, 'error' => '商品不存在: ' . $product_id]);
            exit;
        }

        $product = $all_products[$product_id];

        if ($product['status'] !== 'on_sale') {
            echo json_encode(['success' => false, 'error' => '商品已下架: ' . $product['name']]);
            exit;
        }

        if ($cart_item['quantity'] > $product['stock']) {
            echo json_encode(['success' => false, 'error' => '商品库存不足: ' . $product['name']]);
            exit;
        }

        $total_price += $product['price'] * $cart_item['quantity'];
        $transaction_items[] = [
            'product_id' => $product_id,
            'product_name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $cart_item['quantity'],
            'seller_id' => $product['seller_id']
        ];

        $product_updates[$product_id] = [
            'seller_id' => $product['seller_id'],
            'new_stock' => $product['stock'] - $cart_item['quantity']
        ];
    }

    $fee = $total_price * $settings['transaction_fee'];
    $final_price = $total_price + $fee;

    $buyer_balance = get_user_balance($user_id);
    if ($buyer_balance < $final_price) {
        echo json_encode(['success' => false, 'error' => '余额不足，无法结算']);
        exit;
    }

    $seller_totals = [];
    foreach ($transaction_items as $it) {
        $sid = $it['seller_id'] ?? '';
        if ($sid === '') continue;
        if (!isset($seller_totals[$sid])) $seller_totals[$sid] = 0;
        $seller_totals[$sid] += $it['price'] * $it['quantity'];
    }

    $ok = change_user_balance($user_id, -$final_price);
    if (!$ok) {
        echo json_encode(['success' => false, 'error' => '扣款失败，余额不足']);
        exit;
    }

    $transaction_id = uniqid();
    $transaction = [
        'buyer_id' => $user_id,
        'items' => $transaction_items,
        'total_price' => $total_price,
        'fee' => $fee,
        'final_price' => $final_price,
        'status' => 'completed',
        'created_at' => date('Y-m-d H:i:s')
    ];
    save_transaction($user_id, $transaction_id, $transaction);

    foreach ($cart as $item_id => $cart_item) {
        $product_id = $cart_item['product_id'];
        $product = $all_products[$product_id];
        $seller_id = $product['seller_id'];
        $quantity = $cart_item['quantity'];
        $new_stock = $product_updates[$product_id]['new_stock'];

        $seller_products = get_user_products($seller_id);
        if (isset($seller_products[$product_id])) {
            if ($new_stock <= 0) {
                delete_product_file($seller_id, $product_id);
            } else {
                $seller_products[$product_id]['stock'] = $new_stock;
                save_product($seller_id, $product_id, $seller_products[$product_id]);
            }
        }

        $buyer_inventory = get_user_inventory($user_id);
        $found = false;
        foreach ($buyer_inventory as $existing_id => $existing_item) {
            if ($existing_item['name'] === $product['name'] && ($existing_item['nbt'] ?? '') === ($product['nbt'] ?? '')) {
                $buyer_inventory[$existing_id]['quantity'] += $quantity;
                save_inventory_item($user_id, $existing_id, $buyer_inventory[$existing_id]);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $new_item_id = uniqid();
            $buyer_inventory[$new_item_id] = [
                'name' => $product['name'],
                'quantity' => $quantity,
                'nbt' => $product['nbt'] ?? ''
            ];
            save_inventory_item($user_id, $new_item_id, $buyer_inventory[$new_item_id]);
        }
    }

    foreach ($seller_totals as $sid => $amt) {
        change_user_balance($sid, $amt);
    }

    save_user_cart($user_id, []);

    echo json_encode([
        'success' => true,
        'message' => '结算成功',
        'total_price' => $total_price,
        'fee' => $fee,
        'final_price' => $final_price,
        'item_count' => count($transaction_items)
    ]);
    exit;
}

function main() {
    init_db();

    $action = $_GET['action'] ?? 'shop';

    switch ($action) {
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                handle_login();
            } else {
                show_login();
            }
            break;

        case 'register':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                handle_register();
            } else {
                show_register();
            }
            break;

        case 'logout':
            logout();
            break;

        case 'shop':
            show_shop();
            break;

        case 'product_detail':
            show_product_detail();
            break;

        case 'buy_product':
            buy_product();
            break;

        case 'shop_detail':
            show_shop_detail();
            break;

        case 'personal_center':
            show_personal_center();
            break;

        case 'cart':
            show_cart();
            break;

        case 'add_to_cart':
            add_to_cart();
            break;

        case 'get_cart_count':
            get_cart_count();
            break;

        case 'update_cart_quantity':
            update_cart_quantity();
            break;

        case 'remove_from_cart':
            remove_from_cart();
            break;

        case 'clear_cart':
            clear_cart();
            break;

        case 'checkout':
            checkout();
            break;

        case 'recharge':
            show_recharge();
            break;

        case 'handle_recharge':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') handle_recharge();
            break;

        case 'withdraw':
            show_withdraw();
            break;

        case 'handle_withdraw':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') handle_withdraw();
            break;

        case 'add_review':
            add_review();
            break;



        case 'add_to_shop':
            show_add_to_shop();
            break;

        case 'transactions':
            show_transactions();
            break;

        case 'open_shop':
            open_shop();
            break;

        case 'my_shop':
            show_my_shop();
            break;

        case 'add_product':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                handle_add_product();
            } else {
                show_add_product();
            }
            break;

        case 'edit_product':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                handle_edit_product();
            } else {
                show_edit_product();
            }
            break;

        case 'toggle_product':
            toggle_product();
            break;

        case 'take_back_product':
            take_back_product();
            break;

        case 'delete_product':
            delete_product();
            break;

        case 'edit_shop':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                handle_edit_shop();
            } else {
                show_edit_shop();
            }
            break;

        case 'admin':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                handle_admin_settings();
            } else {
                show_admin();
            }
            break;

        case 'toggle_admin':
            toggle_admin();
            break;

        case 'close_shop':
            close_shop();
            break;


        case 'handle_add_to_shop':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                handle_add_to_shop();
            }
            break;

        case 'api_add_items':
            handle_api_add_items();
            break;

        case 'api_remove_items':
            handle_api_remove_items();
            break;

        case 'api_query_items':
            handle_api_query_items();
            break;

        case 'api_clear_items':
            handle_api_clear_items();
            break;

        case 'api_get_item_info':
            handle_api_get_item_info();
            break;

        case 'api_change_password':
            handle_api_change_password();
            break;

        case 'change_avatar':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') handle_change_avatar();
            break;

        case 'media':
            handle_media();
            break;

        default:
            if (isset($_SESSION['user_id'])) {
                header('Location: ?action=shop');
            } else {
                header('Location: ?action=login');
            }
            exit;
    }
}

function handle_api_add_items() {
    header('Content-Type: application/json');

    $token = $_GET['token'] ?? '';
    $gamename = $_GET['gamename'] ?? '';
    $items_str = $_GET['items'] ?? '';

    if (!$token || !$gamename || !$items_str) {
        echo json_encode([
            'success' => false,
            'error' => '缺少必要参数'
        ]);
        exit;
    }

    $valid_token = get_global_settings()['api_token'];
    if ($token !== $valid_token) {
        echo json_encode([
            'success' => false,
            'error' => 'token错误'
        ]);
        exit;
    }

    $gamename = find_gamename_by_gamename($gamename);
    if (!$gamename) {
        echo json_encode([
            'success' => false,
            'error' => '用户不存在（游戏名未找到）'
        ]);
        exit;
    }

    $items = [];
    $item_pairs = explode('|', $items_str);

    foreach ($item_pairs as $item_pair) {
        $parts = explode(':', $item_pair);

        if (count($parts) >= 3) {

            $item_name = '';
            $quantity = 0;
            $nbt = '';

            for ($i = 0; $i < count($parts); $i++) {
                if (is_numeric($parts[$i])) {
                    $quantity = intval($parts[$i]);
                    $item_name_parts = array_slice($parts, 0, $i);
                    $item_name = implode(':', $item_name_parts);
                    $nbt_parts = array_slice($parts, $i + 1);
                    $nbt = implode(':', $nbt_parts);
                    break;
                }
            }

            if ($quantity == 0 && count($parts) >= 2) {
                $quantity = intval($parts[count($parts) - 1]);
                $item_name_parts = array_slice($parts, 0, count($parts) - 1);
                $item_name = implode(':', $item_name_parts);
            }

            if ($item_name && $quantity > 0) {
                $items[] = [
                    'name' => $item_name,
                    'quantity' => $quantity,
                    'nbt' => $nbt
                ];
            }
        }
    }

    if (empty($items)) {
        echo json_encode([
            'success' => false,
            'error' => '物品格式错误'
        ]);
        exit;
    }

    $inventory = get_user_inventory($gamename);

    $current_inventory_count = count($inventory);

    if ($current_inventory_count > 9) {
        echo json_encode([
            'success' => false,
            'error' => '物品栏已满，最多只能有9种不同的物品'
        ]);
        exit;
    }

    $new_items_count = count($items);
    $final_count = $current_inventory_count + $new_items_count;

    if ($final_count > 9) {
        echo json_encode([
            'success' => false,
            'error' => '物品栏已满，最多只能有9种不同的物品'
        ]);
        exit;
    }

    ensure_user_media_dirs($gamename);

    foreach ($items as $item) {
        $found_item_id = null;
        foreach ($inventory as $item_id => $existing_item) {
            if ($existing_item['name'] === $item['name'] && $existing_item['nbt'] === $item['nbt']) {
                $found_item_id = $item_id;
                break;
            }
        }

        if ($found_item_id) {
            $inventory[$found_item_id]['quantity'] += $item['quantity'];
            save_inventory_item($gamename, $found_item_id, $inventory[$found_item_id]);
        } else {
            $item_id = uniqid();
            save_inventory_item($gamename, $item_id, [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'nbt' => $item['nbt']
            ]);
        }
    }

    $added_items = [];
    foreach ($items as $item) {
        $added_items[] = [
            'name' => $item['name'],
            'chinese_name' => get_item_chinese_name($item['name']),
            'quantity' => $item['quantity'],
            'nbt' => $item['nbt'],
            'attributes' => parse_nbt_to_attributes($item['nbt'], $item['name'])
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => '物品添加成功',
        'added_items' => $added_items
    ]);
    exit;
}

function handle_api_remove_items() {
    header('Content-Type: application/json');

    $token = $_GET['token'] ?? '';
    $gamename = $_GET['gamename'] ?? '';
    $items_str = $_GET['items'] ?? '';

    if (!$token || !$gamename || !$items_str) {
        echo json_encode([
            'success' => false,
            'error' => '缺少必要参数'
        ]);
        exit;
    }

    $valid_token = get_global_settings()['api_token'];
    if ($token !== $valid_token) {
        echo json_encode([
            'success' => false,
            'error' => 'token错误'
        ]);
        exit;
    }

    $gamename = find_gamename_by_gamename($gamename);
    if (!$gamename) {
        echo json_encode([
            'success' => false,
            'error' => '用户不存在（游戏名未找到）'
        ]);
        exit;
    }

    $items = [];
    $item_pairs = explode('|', $items_str);

    foreach ($item_pairs as $item_pair) {
        $parts = explode(':', $item_pair);

        if (count($parts) >= 2) {
            $item_name = $parts[0];
            $quantity = intval($parts[1]);

            $nbt_parts = array_slice($parts, 2);
            $nbt = implode(':', $nbt_parts);

            if ($item_name && $quantity > 0) {
                $items[] = [
                    'name' => $item_name,
                    'quantity' => $quantity,
                    'nbt' => $nbt
                ];
            }
        }
    }

    if (empty($items)) {
        echo json_encode([
            'success' => false,
            'error' => '物品格式错误'
        ]);
        exit;
    }

    $inventory = get_user_inventory($gamename);

    foreach ($items as $item) {
        $found = false;
        $found_item_id = null;
        $found_quantity = 0;

        foreach ($inventory as $item_id => $existing_item) {
            if ($existing_item['name'] === $item['name'] && $existing_item['nbt'] === $item['nbt']) {
                if ($existing_item['quantity'] >= $item['quantity']) {
                    $found = true;
                    $found_item_id = $item_id;
                    $found_quantity = $existing_item['quantity'];
                }
                break;
            }
        }

        if (!$found) {
            echo json_encode([
                'success' => false,
                'error' => '物品不足或不存在：' . $item['name']
            ]);
            exit;
        }
    }

    foreach ($items as $item) {
        foreach ($inventory as $item_id => $existing_item) {
            if ($existing_item['name'] === $item['name'] && $existing_item['nbt'] === $item['nbt']) {
                $new_quantity = $existing_item['quantity'] - $item['quantity'];

                if ($new_quantity <= 0) {
                    delete_inventory_item($gamename, $item_id);
                    unset($inventory[$item_id]);
                } else {
                    $existing_item['quantity'] = $new_quantity;
                    save_inventory_item($gamename, $item_id, $existing_item);
                    $inventory[$item_id] = $existing_item;
                }
                break;
            }
        }
    }


    $inventory = get_user_inventory($gamename);
    $items_data = [];
    foreach ($inventory as $item_id => $item) {
        $items_data[] = [
            'name' => $item['name'],
            'chinese_name' => get_item_chinese_name($item['name']),
            'quantity' => $item['quantity'],
            'nbt' => $item['nbt'],
            'attributes' => parse_nbt_to_attributes($item['nbt'], $item['name'])
        ];
    }

    $removed_items = [];
    foreach ($items as $item) {
        $removed_items[] = [
            'name' => $item['name'],
            'chinese_name' => get_item_chinese_name($item['name']),
            'quantity' => $item['quantity'],
            'nbt' => $item['nbt'],
            'attributes' => parse_nbt_to_attributes($item['nbt'], $item['name'])
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => '物品扣除成功',
        'items' => $items_data,
        'removed_items' => $removed_items
    ]);
    exit;
}

function handle_api_query_items() {
    header('Content-Type: application/json');

    $token = $_GET['token'] ?? '';
    $gamename = $_GET['gamename'] ?? '';

    if (!$token || !$gamename) {
        echo json_encode([
            'success' => false,
            'error' => '缺少必要参数'
        ]);
        exit;
    }

    $valid_token = get_global_settings()['api_token'];
    if ($token !== $valid_token) {
        echo json_encode([
            'success' => false,
            'error' => 'token错误'
        ]);
        exit;
    }

    $gamename = find_gamename_by_gamename($gamename);
    if (!$gamename) {
        echo json_encode([
            'success' => false,
            'error' => '用户不存在（游戏名未找到）'
        ]);
        exit;
    }

    $inventory = get_user_inventory($gamename);
    $items_data = [];

    foreach ($inventory as $item_id => $item) {
        $items_data[] = [
            'name' => $item['name'],
            'chinese_name' => get_item_chinese_name($item['name']),
            'quantity' => $item['quantity'],
            'nbt' => $item['nbt'],
            'attributes' => parse_nbt_to_attributes($item['nbt'], $item['name'])
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => '查询成功',
        'items' => $items_data
    ]);
    exit;
}

function handle_api_clear_items() {
    header('Content-Type: application/json');

    $token = $_GET['token'] ?? '';
    $gamename = $_GET['gamename'] ?? '';

    if (!$token || !$gamename) {
        echo json_encode([
            'success' => false,
            'error' => '缺少必要参数'
        ]);
        exit;
    }

    $valid_token = get_global_settings()['api_token'];
    if ($token !== $valid_token) {
        echo json_encode([
            'success' => false,
            'error' => 'token错误'
        ]);
        exit;
    }

    $gamename = find_gamename_by_gamename($gamename);
    if (!$gamename) {
        echo json_encode([
            'success' => false,
            'error' => '用户不存在（游戏名未找到）'
        ]);
        exit;
    }

    $inventory = get_user_inventory($gamename);
    $cleared_items = [];

    foreach ($inventory as $item_id => $item) {
        $cleared_items[] = [
            'name' => $item['name'],
            'chinese_name' => get_item_chinese_name($item['name']),
            'quantity' => $item['quantity'],
            'nbt' => $item['nbt'],
            'attributes' => parse_nbt_to_attributes($item['nbt'], $item['name'])
        ];
        delete_inventory_item($gamename, $item_id);
    }

    echo json_encode([
        'success' => true,
        'message' => '物品栏已清空',
        'cleared_items' => $cleared_items
    ]);
    exit;
}

function handle_api_get_item_info() {
    header('Content-Type: application/json');

    $token = $_GET['token'] ?? '';
    $item_name = $_GET['item_name'] ?? '';
    $nbt = $_GET['nbt'] ?? '';

    if (!$token || !$item_name) {
        echo json_encode([
            'success' => false,
            'error' => '缺少必要参数'
        ]);
        exit;
    }

    $valid_token = get_global_settings()['api_token'];
    if ($token !== $valid_token) {
        echo json_encode([
            'success' => false,
            'error' => 'token错误'
        ]);
        exit;
    }

    $chinese_name = get_item_chinese_name($item_name);
    $attributes = parse_nbt_to_attributes($nbt, $item_name);

    echo json_encode([
        'success' => true,
        'message' => '物品信息获取成功',
        'item_name' => $item_name,
        'chinese_name' => $chinese_name,
        'attributes' => $attributes
    ]);
    exit;
}

function handle_api_change_password() {
    header('Content-Type: application/json');

    $token = $_GET['token'] ?? '';
    $gamename = $_GET['gamename'] ?? '';
    $new_password = $_GET['new_password'] ?? '';

    if (!$token || !$gamename || !$new_password) {
        echo json_encode([
            'success' => false,
            'error' => '缺少必要参数'
        ]);
        exit;
    }

    $valid_token = get_global_settings()['api_token'];
    if ($token !== $valid_token) {
        echo json_encode([
            'success' => false,
            'error' => 'token错误'
        ]);
        exit;
    }

    $user = get_shop_user($gamename);

    if (!$user) {
        $settings = get_global_settings();
        if (!$settings['allow_web_registration']) {
            echo json_encode([
                'success' => false,
                'error' => '网页注册已关闭，无法自动创建账户'
            ]);
            exit;
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $new_user = [
            'gamename' => $gamename,
            'password' => $hashed_password,
            'balance' => 0.0,
            'role' => '',
            'has_shop' => false,
            'avatar' => '',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $user_file = SHOP_USERS_DIR . '/' . $gamename . '.json';
        if (!is_dir(dirname($user_file))) {
            mkdir(dirname($user_file), 0775, true);
        }
        file_put_contents($user_file, json_encode($new_user, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        ensure_user_media_dirs($gamename);

        echo json_encode([
            'success' => true,
            'message' => '用户已创建并设置密码',
            'user_created' => true
        ]);
        exit;
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user['password'] = $hashed_password;

        $user_file = SHOP_USERS_DIR . '/' . $gamename . '.json';
        file_put_contents($user_file, json_encode($user, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        echo json_encode([
            'success' => true,
            'message' => '密码已更新',
            'user_created' => false
        ]);
        exit;
    }
}

function handle_media() {
    $file = $_GET['file'] ?? '';
    if (!$file) {
        http_response_code(400);
        echo '文件参数缺失';
        exit;
    }

    $file = urldecode($file);

    if (strpos($file, '..') !== false || strpos($file, '/') === 0 || strpos($file, '\\') !== false) {
        http_response_code(403);
        echo '非法文件路径';
        exit;
    }

    $prefix = 'shop_data/Media/';
    if (strpos($file, $prefix) === 0) {
        $file = substr($file, strlen($prefix));
    }

    $file_normalized = str_replace('/', DIRECTORY_SEPARATOR, $file);
    $base_dir = SHOP_MEDIA_DIR;

    $full_path = $base_dir . DIRECTORY_SEPARATOR . $file_normalized;

    $base_dir_real = realpath($base_dir);
    $full_path_real = realpath($full_path);

    if (!$full_path_real || strpos($full_path_real, $base_dir_real) !== 0) {
        http_response_code(404);
        echo '文件不存在或路径非法';
        exit;
    }

    if (!file_exists($full_path_real)) {
        http_response_code(404);
        echo '文件不存在';
        exit;
    }

    $ext = strtolower(pathinfo($full_path_real, PATHINFO_EXTENSION));
    $mime_types = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
    ];
    $content_type = $mime_types[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $content_type);
    header('Content-Length: ' . filesize($full_path_real));
    readfile($full_path_real);
    exit;
}

function handle_change_avatar() {
    check_login();
    $gamename = $_SESSION['user_id'];

    ob_start();

    if (!isset($_FILES['avatar'])) {
        ob_end_clean();
        page_header('头像上传失败');
        echo '<div style="text-align: center; padding: 30px;">';
        echo '<h3>头像上传失败</h3>';
        echo '<p>请选择一个图片文件。</p>';
        echo '<a href="?action=personal_center" class="btn">返回个人中心</a>';
        echo '</div>';
        page_footer();
        exit;
    }

    $error = $_FILES['avatar']['error'];
    if ($error !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '临时文件夹不存在',
            UPLOAD_ERR_CANT_WRITE => '写入磁盘失败',
            UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止'
        ];
        $message = $error_messages[$error] ?? '上传错误代码：' . $error;
        ob_end_clean();
        page_header('头像上传失败');
        echo '<div style="text-align: center; padding: 30px;">';
        echo '<h3>头像上传失败</h3>';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        echo '<a href="?action=personal_center" class="btn">返回个人中心</a>';
        echo '</div>';
        page_footer();
        exit;
    }

    $uploaded = upload_image_for_user($gamename, 'avatar', 'Icon');
    if (!$uploaded) {
        ob_end_clean();
        page_header('头像上传失败');
        echo '<div style="text-align: center; padding: 30px;">';
        echo '<h3>头像上传失败</h3>';
        echo '<p>文件格式不支持或文件过大（最大5MB）。</p>';
        echo '<a href="?action=personal_center" class="btn">返回个人中心</a>';
        echo '</div>';
        page_footer();
        exit;
    }

    $avatar_url = '?action=media&file=' . urlencode($uploaded);
    update_user_avatar($gamename, $avatar_url);

    ob_end_clean();
    $_SESSION['flash_message'] = '头像更换成功！';
    $_SESSION['flash_type'] = 'success';
    header('Location: ?action=personal_center');
    exit;
}

main();
