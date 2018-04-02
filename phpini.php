<?php

/*	Класс для автоматической правки php.ini
	Требования: PHP 5.0+ (или 7.0+)
	Заменяет заданные директивы на новые (если они имеются), или же добавляет, если их нет совсем.
	пример использования: см. index.php
*/
class php_ini_patcher {

	// путь к файлу php.ini
	public $filename;
	
	// фактическое кол-во модифицированных и добавленных полей
	public $modified, $added = 0;
	// результат
	public $result = array();
	
	/*	Прочитать конфиг, обновить его новыми данными и подготовить к сохранению.
			$filename - путь к php.ini
			$settings - (строка) обновляемые настройки вида: 
				[секция]
				директивы
				директивы
				[секция]
				директивы
				директивы
				...
			Директивы должны быть однострочными. Лишние пробелы и переносы строки не играют роли.
	*/
	public function __construct($filename = '/etc/php.ini', $settings = '')
	{
		$this->filename = $filename;
		$this->result = array();
		$this->modified = $this->added = 0;
		if (!file_exists($filename)) return;
		$z = array();
		$settings = preg_split('#[\r\n]+#', trim($settings));
		$name = '[php]';
		foreach ($settings as $k=>$v)
		{
			$v = trim($v);
			if ($v{0}=='[')
			{$name = strtolower($v);}
				else
			{$z[$name][] = $v;}
		}
		$s = file_get_contents($this->filename);
		preg_match_all('#(^|\n)\[[^\[\]]+\].*?(?=\n\[|$)#s', $s, $m);
		foreach ($m[0] as &$v)
		{
			preg_match('#\[.*#', $v, $mm);
			$section = strtolower(trim($mm[0]));
			if (!$z[$section]) continue;
			$add = array();
			foreach ($z[$section] as $vv)
			{
				if (!preg_match('#([^\s;\#=]+)\s*=#', $vv, $mm)) continue;
				$v = preg_replace('#(?<=^|\n)[ \t\#;]*'.preg_quote($mm[1], '#').'\s*=.*#', ltrim($vv), $v, 1, $c);
				$this->modified += $c;
				if (!$c) $add[] = ltrim($vv);
			}
			if ($add)
			{
				preg_match('#(?:^|\n)[\t ]*'.preg_quote($section, '#').'[\t ]*\r?\n#i', $v, $mm, PREG_OFFSET_CAPTURE);
				$x = $mm[0][1]+strlen($mm[0][0]);
				$v = substr($v, 0, $x).implode("\n", $add)."\n\n".substr($v, $x);
			}
			$this->added += count($add);
		}
		unset($v);
		$this->result = $m[0];
	}
	
	/*	Сохранить измененный конфиг в исходный файл. Предыдущий файл будет забекаплен.
		Вернет кол-во записанных байт или NULL при ошибке.
	*/
	public function save()
	{
		if ($this->result && is_writable($this->filename))
		{
			copy($this->filename, $this->filename.'.backup');
			return file_put_contents($this->filename, implode($this->result));
		}
	}
}
