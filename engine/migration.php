<?php 
    /**
     * Класс сравнивает две БД на наличие таблиц и их столбцов
     * и добавляет таблицы, которых нет на прод базе
     * 
     * При сравенении таблиц добавляет новые столбцы, которых не было ранее
     * 
     * При наличии столбцов с одинаковым названием (на проде и тестовой), но с разными типами 
     * (что в реальности вряд ли возможно) изменяет название столбца на проде как 
     * column_old и впоследствии преобразует данные из него в column в соответствии с новым типом
     * Данная возможность отключается переменной $create_old
     * 
     * @param $db_test_conn подключение к тестовой БД
     * @param $db_test данные тестовой БД (таблицы и их структура)
     * @param $db_prod_conn подключение к прод БД
     * @param $db_prod данные прод БД (таблицы и их структура)
     * @param $create_old переключатель (создавать ли при сравнении стобцы _old и последующей миграции данных в актуальные)
     * 
     * @method showTables отображает все таблицы в БД
     * @method showColumns отображает все колонки и их типы в таблице
     * @method showTableStructure отображает всю информацию по БД (таблицы + столбцы)
     * @method dbcomparison сравнивает две БД 
     * @method addTable2DB получает информацию о тестовой таблице и добавляет ее на прод
     * @method addColumn2Table добавляет колонку в существующей таблице на проде в соответствии с тестовой
     * @method modifierData отвечает за преобразование данных из column_old в column 
     * является примером реализации, в нем описаны всего несколько типов и он может быть масштабирован
     * !!! возможна потеря данных при преобразовании string в int и подобные
     */
    final class migration {
        private $db_test_conn;
        private $db_test;
        private $db_prod_conn;
        private $db_prod;
        private $create_old = false;

        public function __construct()
        {
            // подключаемся к двум БД
            $this->db_test_conn = new db(db_test);
            $this->db_prod_conn = new db(db_prod);
            
            // Получаем данные по всем таблицам для дальнейшего сравнения страктур таблиц между БД
            $this->db_test = $this->showTableStructure($this->db_test_conn);
            $this->db_prod = $this->showTableStructure($this->db_prod_conn);

            // сравнение таблиц
            $this->dbcomparison();
        }
        public function showTables(db $db) : array
        {
            $sql = "SHOW TABLES";
            return $db->getArraySQL($sql, PDO::FETCH_COLUMN);
        }
        public function showColumns(db $db, string $table) : array 
        {
            $sql = "DESCRIBE `" . $table . "`";
            $tableData = $db->getArraySQL($sql, PDO::FETCH_ASSOC);
            $tableColumns = [];
            foreach ($tableData as $key => $column) {
                $tableColumns[$column['Field']] = $column;
            }

            return $tableColumns;
        }
        public function showTableStructure (db $db) : array
        {
            $tables = $this->showTables($db);
            $db_data = [];
            foreach ($tables as $key => $table) {
                $db_data[$table] = $this->showColumns($db, $table);
            }
            return $db_data;
        }
        public function dbcomparison ()
        {
            foreach ($this->db_test as $table => $columns) {
                // проверяем, есть ли такая таблица на проде, если нет, то создаем новую
                if (!array_key_exists($table, $this->db_prod)) {
                    $this->addTable2DB($table);
                    echo "Создаем таблицу $table \n";
                    continue;
                }
                // Проверяем на совпадение всех столбцов
                $after_column = ' FIRST';
                foreach ($columns as $column => $data) {
                    // если нет столбца, то создаем новый
                    if (!array_key_exists($column, $this->db_prod[$table])) {
                        $this->addColumn2Table($table, $column, $data, $after_column);
                        echo "Добавляем новый столбец $column в таблице $table \n";
                        continue;
                    }
                    $after_column = " AFTER `$column`";

                    if ($this->create_old) {
                        // если тип столбца не отличается, то пропускаем
                        if (!array_diff($data, $this->db_prod[$table][$column])) continue;
                        // в противном случае нужно 
                        // 1) переименовываем его на проде в _old
                        $sql_change_name = "ALTER TABLE $table RENAME COLUMN `$column` TO `" . $column . "_old`";
                        $this->db_prod_conn->sendSQL($sql_change_name);
                        // 2) создаем новый столбец с актуальными параметрами
                        $this->addColumn2Table($table, $column, $data, $after_column);
                        
                        // переносим значения с преобразованием из _old в актуальный тип
                        $sql_old_2_new = $this->modifierData($table, $column, $data['Type']);
                        $this->db_prod_conn->sendSQL($sql_old_2_new);
                    }
                }
            }
            echo "Миграция успешно выполнена \n";
        }
        public function addTable2DB (string $table) 
        {
            // получаем sql запрос для создания таблицы
            $sql = "SHOW CREATE TABLE " . $table;
            $create_table_sql = $this->db_test_conn->getSingleSQL($sql)['Create Table'];
            // отправляем его на прод 
            $this->db_prod_conn->sendSQL($create_table_sql);
        }
        public function addColumn2Table (string $table, string $column, array $data, string $after) 
        {
            $sql = "ALTER TABLE `$table` ADD `$column`";
            $sql .= " " . $data['Type'];
            $sql .= ($data['Null'] === 'NO' ? ' NOT NULL' : 'NULL');
            $sql .= ($data['Default'] !== NULL ? ' DEFAULT ' . $data['Default'] : '');
            $sql .= ($data['Extra'] === 'auto_increment' ? ' AUTO_INCREMENT' : '');
    
            if ($data['Key'] === 'PRI') $sql .= ", ADD PRIMARY KEY (`$column`)";
            if (!empty($after)) $sql .= $after;

            $this->db_prod_conn->sendSQL($sql);
        }
        public function modifierData (string $table, string $column, string $new_type) : string
        {
            $arr = [
                'numbers' => ['int', 'float'],
                'text' => ['text']
            ];
            
            switch ($new_type) {
                case in_array($new_type, $arr['numbers']):
                    $sql = "UPDATE `$table` SET `$column` = CONVERT(REGEXP_REPLACE(`" . $column . "_old`, '[^0-9]', ''), UNSIGNED)";
                    break;
                case in_array($new_type, $arr['text']):
                    $sql = "UPDATE `$table` SET `$column` = CONVERT(`" . $column . "_old`, CHAR)";
                    break;
                default:
                    $sql = '';
                    break;
            }
            return $sql;
        }
    }