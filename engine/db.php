<?php
    /**
     * Подключение к БД и обработки SQL запросов через PDO
     */
    class db
    {
        private $db;
        public function __construct($db_data) // конструктор
        {
            $this->dbConnect($db_data);
        }
        public function dbConnect($db_data) // подключение к БД
        {
            $host = "mysql:host=" . $db_data['host'] . ";port=" . $db_data['port'] . ";dbname=" . $db_data['db'];
            $user = $db_data['user'];
            $pass = $db_data['pass'];

            try {
                $this->db = new PDO($host, $user, $pass);
                // установка режима вывода ошибок
                $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $data['db_connect'] = true;
            }
            catch (PDOException $e) {
                echo "Connection failed: " . $e->getMessage();
                $data['db_connect'] = false;
                $data['error'] = $e->getMessage();
            }

            return $data['db_connect'];
        }
        public function getArraySQL($sql, $mode = PDO::FETCH_DEFAULT) : array // получение массива данных из БД 
        {
            $result = $this->db->query($sql);
            return $result->fetchAll($mode);
        }
        public function getSingleSQL($sql, $mode = PDO::FETCH_DEFAULT) : array // получение строки из БД
        {
            $result = $this->db->query($sql);
            return $result->fetch($mode);
        }
        public function sendSQL($sql) : int // отправка запроса типа INSERT или UPDATE, результатом будет число удачных изменений
        {
            $result = $this->db->query($sql);
            $result->fetch(PDO::FETCH_BOUND);
            return $result->rowCount();
        }
        public function stringEscape(string $str = '') : string // экранирование строки
        {
            return $this->db->quote($str);
        }
        public function lastInsertID(string $table = '') : string|bool {
            return $this->db->lastInsertId($table);
        }

    }
    