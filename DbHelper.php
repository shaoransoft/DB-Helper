<?php
/* Shaoran DbHelper framework 4.0 */
/* -- Shaoransoft Develop -- */

class DbHelper {
  private static $conn;
  private $dsn = null;
  private $user = 'root';
  private $pwd = '';

  private $iconvEnable = false;
  private $iconvInChar = 'UTF-8';
  private $iconvOutChar = 'UTF-8';

  private $method = 0;
  private $entity = null;
  private $attr = null;
  private $where = null;
  private $orderBy = null;
  private $groupBy = null;
  private $limit = null;
  private $cmd = null;
  private $params = [];
  private $values = [];

  public function setDsn($dsn) {
    if (isset($dsn)) $this->dsn = $dsn;
    return $this;
  }

  public function setUsername($user) {
    if (isset($user)) $this->user = $user;
    return $this;
  }

  public function setPassword($pwd) {
    if (isset($pwd)) $this->pwd = $pwd;
    return $this;
  }

  public function setAuth($user, $pwd) {
    setUsername($user)->setPassword($pwd);
    return $this;
  }

  public function setIconv($enable, $inChar, $outChar) {
    if (isset($enable)) $this->iconvEnable = $enable;
    if (isset($inChar)) $this->iconvInChar = $inChar;
    if (isset($outChar)) $this->iconvOutChar = $outChar;
    return $this;
  }

  public function connect() {
    if (empty($this->dsn) || empty($this->user)) {
      echo 'no dsn connection';
      exit;
    }
    try {
      self::$conn = new PDO($this->dsn, $this->user, $this->pwd, [
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8',
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
      ]);
    }
    catch (PdoException $e) {
      echo $e->getMessage();
      exit;
    }
  }

  public function isConnect() {
    return isset(self::$conn);
  }

  public function select($entity, $attr) {
    $this->method = 0;
    $this->clearAll();
    if (!is_null($entity)) {
      $this->entity = $entity;
      $this->attr = is_null($attr) ? '*' : is_array($attr) ? join(',', $attr) : $attr;
    }
  }

  public function update($entity, $attr) {
    $this->method = 1;
    $this->clearAll();
    if (!is_null($entity)) {
      $this->entity = $entity;
      if (!is_null($attr) && is_array($attr)) {
        $i = 0;
        foreach ($attr as $k => $v) {
          if (isset($v)) {
            if ($i < count($attr) && $i > 0) $attrs .= ', ';
            $this->attr .= "{$k}=?";
            $this->values[] = $v;
            $i++;
          }
        }
      }
    }
  }

  public function insert($entity, $attr) {
    $this->method = 2;
    $this->clearAll();
    if (!is_null($entity)) {
      $this->entity = $entity;
      $attrs = [];
      $vals = [];
      if (!is_null($attr) && is_array($attr)) {
        foreach ($attr as $k => $v) {
          if (isset($v)) {
            $attrs[] = $k;
            $vals[] = '?';
            $this->values[] = $v;
          }
        }
      }
      $this->attr = '('.join(',', $attrs).') VALUES('.join(',', $vals).')';
    }
  }

  public function delete($entity) {
    $this->method = 3;
    $this->clearAll();
    if (!is_null($entity)) $this->entity = $entity;
  }

  public function where($attr) {
    $cmd = null;
    $paramCount = $this->paramCount($attr);
    if (!is_null($attr) && is_array($attr)) {
      $i = 0;
      foreach ($attr as $k => $v) {
        if (is_array($v)) {
          if (count($v) > 0) {
            foreach ($v as $subK => $subV) {
              if (isset($subV)) {
                switch (strtoupper($subK)) {
                  case 'LIKE':
                    if ($i < 1) $cmd .= '(';
                    if ($i > 0) $cmd .= ' OR ';
                    $cmd .= "{$k} LIKE ?";
                    if ($i == $paramCount['LIKE'] - 1) $cmd .= ')';
                    break;
                  case 'ISNOT':
                    if ($i > 0) $cmd .= ' AND ';
                    $cmd .= "{$k}!=?";
                    break;
                  case 'IN':
                  case 'NOTIN':
                    if ($i > 0) $cmd .= ' AND ';
                    $cmd .= "{$k} ".strtoupper($subK)."(";
                    if (is_array($subV)) {
                      $j = 0;
                      foreach ($subV as $subVal) {
                        if ($j < count($subV) && $j > 0) $cmd .= ',';
                        $cmd .= '?';
                        $j++;
                      }
                    }
                    else $cmd .= '?';
                    $cmd .= ')';
                    break;
                  case 'MORE':
                    if ($i > 0) $cmd .= ' AND ';
                    $cmd .= "{$k}>?";
                    break;
                  case 'LESS':
                    if ($i > 0) $cmd .= ' AND ';
                    $cmd .= "{$k}<?";
                    break;
                  case 'IS':
                  default:
                    if ($i > 0) $cmd .= ' AND ';
                    $cmd .= "{$k}=?";
                    break;
                }
                if (is_array($subV) && count($subV) > 0) {
                  foreach ($subV as $getSubV) {
                    $this->values[] = $getSubV;
                  }
                }
                else $this->values[] = $subV;
                $i++;
              }
            }
          }
        }
        else {
          if (isset($v)) {
            if ($i > 0) $cmd .= ' AND ';
            $cmd .= "{$k}=?";
            $this->values[] = $v;
            $i++;
          }
        }
      }
    }
    else if (isset($attr)) $cmd = $attr;
    if (!is_null($cmd))
      $this->where = is_null($this->where) ? " WHERE {$cmd}" : " {$this->where} AND {$cmd}";
    return $this;
  }

  public function appendWhere($sql) {
    $this->where .= is_null($this->where) ? ' WHERE ' : ' AND ';
    if (isset($sql)) $this->where .= $sql;
    return $this;
  }

  public function orderBy($attr) {
    if (!is_null($attr) && is_array($attr)) {
      $this->orderBy .= ' ORDER BY ';
      $i = 0;
      foreach ($attr as $k => $v) {
        if ($i < count($attr) && $i > 0) $this->orderBy .= ',';
        $this->orderBy .= $k;
        switch (strtoupper($v)) {
          default:
          case '09':
          case 'AZ':
          case 'ASC':
          case '<':
            $this->orderBy .= ' ASC';
            break;
          case '90':
          case 'ZA':
          case 'DESC':
          case '>':
            $this->orderBy .= ' DESC';
            break;
        }
        $i++;
      }
    }
  }

  public function groupBy($attr) {
    if (!is_null($attr) && is_array($attr)) {
      $this->groupBy .= ' GROUP BY ';
      $i = 0;
      foreach ($attr as $k) {
        if ($i < count($attr) && $i > 0) $this->groupBy .= ',';
        $this->groupBy .= $k;
        $i++;
      }
    }
  }

  public function limit($limit, $offset = 25) {
    if (!is_null($limit) && is_numeric($limit)) {
      $this->limit = " LIMIT {$limit}";
      if (isset($offset) && is_numeric($offset)) $this->limit .= ", {$offset}";
    }
  }

  public function getCommand() {
    $this->createCmd();
    return $this->cmd;
  }

  public function getValue() {
    return $this->values;
  }

  public function execute() {
    $this->createCmd();
    switch ($this->method) {
      case 0:
        $result = [];
        if (!is_null($this->cmd)) {
          $query = $this->executeCmd();
          if ($query != null && $query->rowCount() > 0) {
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
              $result[] = $this->charConvert($row, true);
            }
          }
        }
        return $result;
        break;
      default:
        $result = false;
        if ($this->cmd != null) {
          $query = $this->executeCmd();
          if ($query != null) $result = $query->rowCount() > 0;
        }
        return $result;
        break;
    }
  }

  private function paramCount($attr) {
    $result = ['LIKE' => 0, 'ISNOT' => 0, 'IN' => 0, 'NOTIN' => 0, 'MORE' => 0, 'LESS' => 0, 'IS' => 0];
    if (isset($attr) && is_array($attr)) {
      foreach ($attr as $k => $v) {
        if (is_array($v)) {
          if (count($v) > 0) {
            foreach ($v as $subK => $subV) {
              if (isset($subV)) $result[strtoupper($subK)]++;
            }
          }
        }
        else
          if (isset($v)) $result['IS']++;
      }
    }
    return $result;
  }

  private function createCmd() {
    switch ($this->method) {
      case 0:
        $this->cmd = "SELECT {$this->attr} FROM {$this->entity}";
        if (!is_null($this->where)) $this->cmd .= $this->where;
        if (!is_null($this->orderBy)) $this->cmd .= $this->orderBy;
        if (!is_null($this->groupBy)) $this->cmd .= $this->groupBy;
        if (!is_null($this->limit)) $this->cmd .= $this->limit;
        break;
      case 1:
        $this->cmd = "UPDATE {$this->entity} SET {$this->attr}";
        if (!is_null($this->where)) $this->cmd .= $this->where;
        break;
      case 2:
        $this->cmd = "INSERT INTO {$this->entity} {$this->attr}";
        break;
      case 3:
        $this->cmd = "DELETE FROM {$this->entity}";
        if (!is_null($this->where)) $this->cmd .= $this->where;
        break;
    }
  }

  private function clearAll() {
    $this->entity = null;
    $this->attr = null;
    $this->where = null;
    $this->orderBy = null;
    $this->groupBy = null;
    $this->limit = null;
    $this->cmd = null;
    $this->params = [];
  }

  private function executeCmd() {
    $query = null;
    $params = $this->method < 1 ? $this->charConvert($this->values) : $this->values;
    try {
      $query = self::$conn->prepare($this->cmd);
      $query->execute($params);
    }
    catch (exception $e) {
      echo $e->getMessage();
      exit;
    }
    return $query;
  }

  private function charConvert($data = [], $revert = false) {
    if (!is_null($data) && is_array($data)) {
      if (!$this->iconvEnable) {
        $inChar = $revert ? $this->iconvOutChar : $this->iconvInChar;
        $outChar = $revert ? $this->iconvInChar : $this->iconvOutChar;
        return array_combine(array_keys($data), array_map(function ($v) use ($inChar, $outChar) {
          return iconv($inChar, $outChar, $v);
        }, $data));
      }
      else return $data;
    }
    return $data;
  }
}
?>
