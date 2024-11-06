<?php 
    /**
     * Класс сравнивает две БД на наличие таблиц и их столбцов
     * и добавляет таблицы, которых нет в прод базе
     * 
     * При сравенении таблиц добавляет новые столбцы, которых не было ранее
     * 
     * При наличии двух столбцов с одинаковыми названием, но разным типом, переименовывает на проде столбец в _old
     * и добавляет новый столбец с акутальным типом
     * 
     * 
     */
    final class migration {
        private $db_test_conn; // тестовая БД
        private $db_test; // структура тестовой БД
        private $db_prod_conn; // прод БД
        private $db_prod; // структура прод БД

        public function __construct() // конструктор
        {
            // подключаемся к двум БД
            $this->db_test_conn = new db(db_test);
            $this->db_prod_conn = new db(db_prod);
            
            /**
             * Получаем данные по всем таблицам для дальнейшего сравнения страктур таблиц между БД
             */
            $this->db_test = $this->showTableStructure($this->db_test_conn);
            $this->db_prod = $this->showTableStructure($this->db_prod_conn);

            $this->dbcomparison();
        }
        public function showTables(db $db) : array // получаем название всех таблиц в БД
        {
            $sql = "SHOW TABLES";
            return $db->getArraySQL($sql, PDO::FETCH_COLUMN);
        }
        public function showColumns(db $db, $table) : array // получаем структуру таблицы
        {
            $sql = "DESCRIBE `" . $table . "`";
            $tableData = $db->getArraySQL($sql, PDO::FETCH_ASSOC);
            $tableColumns = [];
            foreach ($tableData as $key => $column) {
                $tableColumns[$column['Field']] = $column;
            }

            return $tableColumns;
        }
        public function showTableStructure (db $db) : array // получаем стуктуру всех таблиц в БД (для дальнейшего сравнения столбцов таблиц)
        {
            $tables = $this->showTables($db);
            $db_data = [];
            foreach ($tables as $key => $table) {
                $db_data[$table] = $this->showColumns($db, $table);
            }
            return $db_data;
        }
        public function dbcomparison () // сравнение стракутур баз данных
        {
            foreach ($this->db_test as $table => $columns) {
                // проверяем, есть ли такая таблица на проде, если нет, то создаем новую
                if (!array_key_exists($table, $this->db_prod)) {
                    $this->addTable2DB($table);
                    continue;
                }
                // Проверяем на совпадение всех столбцов
                $after_column = 'FIRST';
                foreach ($columns as $column => $data) {
                    // если нет столбца, то создаем новый
                    if (!array_key_exists($column, $this->db_prod[$table])) {
                        $this->addColumn2Table($table, $column, $data, $after_column);
                        continue;
                    }
                    // если тип столбца не отличается, то пропускаем
                    if (!array_diff($data, $this->db_prod[$table][$column])) continue;
                    // в противном случае нужно 
                    // 1) переименовываем его на проде в _old
                    $sql_change_name = "ALTER TABLE $table RENAME COLUMN `$column` TO `" . $column . "_old`";
                    $this->db_prod_conn->sendSQL($sql_change_name);
                    // 2) создаем новый столбец с актуальными параметрами
                    $this->addColumn2Table($table, $column, $data, $after_column);
                    $after_column = " AFTER `$column`";
                }
            }
        }
        public function addTable2DB ($table) // если не было таблицы на проде, то создаем ее
        {
            // получаем sql запрос для создания таблицы
            $sql = "SHOW CREATE TABLE " . $table;
            $create_table_sql = $this->db_test_conn->getSingleSQL($sql)['Create Table'];
            // отправляем его на прод 
            $this->db_prod_conn->sendSQL($create_table_sql);
        }
        public function addColumn2Table ($table, $column, $data, $after) // добавление столбца на прод БД
        {
            $sql = "ALTER TABLE `$table` ADD `$column`";
            $sql .= " " . $data['Type'];
            $sql .= ($data['Null'] === 'NO' ? ' NOT NULL' : 'NULL');
            $sql .= ($data['Default'] !== NULL ? ' DEFAULT ' . $data['Default'] : '');
            $sql .= ($data['Extra'] === 'auto_increment' ? ' AUTO_INCREMENT' : '');
    
            if ($data['Key'] === 'PRI') $sql .= ", ADD PRIMARY KEY (`$column`)";

            $this->db_prod_conn->sendSQL($sql);
        }
        public function addNewData2prod () // 
        {}
    }