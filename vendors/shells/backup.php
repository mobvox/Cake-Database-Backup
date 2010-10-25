<?php
/**
 * Cake Dbo Database Backup
 *
 * Backups structure and data from cake's database dbo datasources supported by Cake dbo package.
 * Works with any cake dbo datasource, like MySQL, PostgreSQL, SQL Server, and others.
 * Usage:
 * $ cake backup
 * To backup all tables structure and data from default datasource
 *
 * TODO
 * Settings to choose datasource, table and output directory
 * 
 * @package		backup
 * @subpackage	backup.vendors.shells
 * @author 		Ariel Patschiki
 * @link		http://www.phpjedi.com.br
 * @license 	http://www.opensource.org/licenses/mit-license.php The MIT License
 */
class BackupShell extends Shell {

/**
 * Contains arguments parsed from the command line.
 *
 * @var array
 * @access public
 */
	var $args;

/**
 * Override main() for help message hook
 *
 * @access public
 */
	function main() {
		App::import('Model', 'CakeSchema');
		
		$dataSourceName = 'default';
		
		$path = APP_PATH . 'webroot/backups/';

		$Folder = new Folder($path, true);
		
		$fileSufix = date('Ymd\_His') . '.sql';
		$file = $path . $fileSufix;
		if (!is_writable($path)) {
			trigger_error('The path "' . $path . '" isn\'t writable!', E_USER_ERROR);
		}
		
		$this->out("Backuping...\n");
		$File = new File($file);
		
		foreach (ConnectionManager::getInstance()->getDataSource($dataSourceName)->listSources() as $table) {
			
			$ModelName = Inflector::classify($table);
			$Model = ClassRegistry::init($ModelName);
			$DataSource = $Model->getDataSource();

			$CakeSchema = new CakeSchema();
			$CakeSchema->tables = array($table => $Model->_schema);
			
			$File->write("\n/* Backuping table schema {$table} */\n");
			$File->write($DataSource->createSchema($CakeSchema, $table) . "\n");
			$File->write("\n/* Backuping table data {$table} */\n");
			
			unset($valueInsert, $fieldInsert);

			$rows = $Model->find('all', array('recursive' => -1));
			$quantity = 0;
			if (sizeOf($rows) > 0) {
				$fields = array_keys($rows[0][$ModelName]);
				$values = array_values($rows);	
				$count = count($fields);

				for ($i = 0; $i < $count; $i++) {
					$fieldInsert[] = $DataSource->name($fields[$i]);
				}
				$fieldsInsertComma = implode(', ', $fieldInsert);

				foreach ($rows as $k => $row) {
					unset($valueInsert);
					for ($i = 0; $i < $count; $i++) {
						$valueInsert[] = $DataSource->value($row[$ModelName][$fields[$i]], $Model->getColumnType($fields[$i]), false);
					}
					$query = array(
						'table' => $DataSource->fullTableName($table),
						'fields' => $fieldsInsertComma,
						'values' => implode(', ', $valueInsert)
					);			
					$File->write($DataSource->renderStatement('create', $query) . ";\n");
					$quantity++;
				}
			}
			
			$this->out('Model "' . $ModelName . '" (' . $quantity . ')');
		}
		$File->close();
		$this->out("\nFile \"" . $file . "\" saved (" . filesize($file) . " bytes)\n");

		if (class_exists('ZipArchive') && filesize($file) > 100) {
			$this->out('Zipping...');
			$zip = new ZipArchive();
			$zip->open($file . '.zip', ZIPARCHIVE::CREATE);
			$zip->addFile($file, $fileSufix);
			$zip->close();
			$this->out("Zip \"" . $file . ".zip\" Saved (" . filesize($file . '.zip') . " bytes)\n");
			$this->out("Removing original file...");
			if (file_exists($file . '.zip') && filesize($file) > 10) {
				unlink($file);
			}
			$this->out("Original file removed.\n");
		}
	}
}
?>