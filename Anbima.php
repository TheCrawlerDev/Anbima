<?php

class Anbima{

  public $cpf = null; // cpf do usuario para a requisição no site

  public $nome = null; // nome do usuario para a requisição no site

  public $context = null; // contexto retornado pela requisição

  public $certificacoes = null;  // retorno final, certificações

  public function __construct($cpf,$nome){
      $this->cpf = $cpf;
      $this->nome = $nome;
      self::get_ambina_contents();  // faz a interação com o site e retorna o codigo da tabela de certificações
      return self::html_contents_2_json(); // transforma a tabela html em uma estrutura de vetor
  }

  public function get_ambina_contents(){
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://certificacao.anbima.com.br/consulta_publica_consolidada_frame.asp');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, sprintf("ordenar=&pagina=&oper=L&prova=&cpf=%s&nome=%s",$this->cpf,str_replace(' ','+',$this->nome)));
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');

    $headers = array();
    $headers[] = 'Connection: keep-alive';
    $headers[] = 'Cache-Control: max-age=0';
    $headers[] = 'Origin: https://certificacao.anbima.com.br';
    $headers[] = 'Upgrade-Insecure-Requests: 1';
    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $headers[] = 'User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/78.0.3904.97 Mobile Safari/537.36';
    $headers[] = 'Sec-Fetch-User: ?1';
    $headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3';
    $headers[] = 'Sec-Fetch-Site: same-origin';
    $headers[] = 'Sec-Fetch-Mode: nested-navigate';
    $headers[] = 'Referer: https://certificacao.anbima.com.br/consulta_publica_consolidada_frame.asp';
    $headers[] = 'Accept-Encoding: gzip, deflate, br';
    $headers[] = 'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch); // retorna o resultado da requisição
    $result = trim(preg_replace('/\s+/', ' ', $result));// remove os espaços e breaklines do html
    $this->context = $result;
    if (curl_errno($ch)) {
        return 'Error:' . curl_error($ch);
    }
    curl_close($ch);
    return $this->context;
  }

  public static function pesquisar($string, $after, $before,$striptags=true){
    // função de pesquisar que substitui uma expressão regular. São passadas duas tags e a função captura o que está entre elas.
  	$subresult = '';
  	if(strpos($string,$after) !== false) {
  		$subresult = substr($string,strpos($string,$after)+strlen($after));
  		$subresult = strchr($subresult,$before,true);
  	}
  	$subresult = str_replace('&nbsp;','',$subresult);
  	return $striptags===true ? strip_tags(trim($subresult)) : trim($subresult);
	}

  public function extract_data_tds($elements){
    $retorno = array();
    foreach($elements as $e){
      $e = strip_tags($e);
      $retorno[] = trim($e);
    }
    return isset($retorno[1]) && strlen($retorno[1])>1 ? array_filter($retorno,'strlen') : array();
  }

  public function html_contents_2_json(){
    $table = $this->pesquisar($this->context, '<td valign="top"> <table border=0 cellspacing=0 cellpadding=1 width="100%">', '</table> </td>', false);
    // pesquisa entre as duas tags o codigo da tabela
    $table = explode('</tr>',$table); // transformando as colunas em um vetor de colunas
    unset($table[0],$table[1]); // removendo os indices da tabela
    $response = array_map(function ($v){
      return $this->extract_data_tds(explode('</td>',$v));
    },$table); // transformando o array em multidimensional tendo como filtro basico o elemento da coluna 1 que é o nome do candidato
    $response = array_filter( $response, function ($v){
      if(count($v)>1) return true;
      else return false;
    } );  // filtrando apenas os arrays com mais de um elemento
    $response = array_map(function ($v){
      return array_combine(
        array('nome','certificacao','data_1_certificacao','data_ult_certificacao','data_vencimento','situacao'),
        $v
      );
    },$response); // inserindo os indices nos arrays
    $this->certificacoes['lenght'] = count($response); // quantidade de certificacoes
    $this->certificacoes['valids'] = array(
        'data' => array_map(function ($v){
          return $v['certificacao'];
        },array_filter( $response, function ($v){
          return $v['situacao']==='Ativa';
        } ))
    );  // certificacoes ativas
    $this->certificacoes['valids']['lenght'] = count($this->certificacoes['valids']['data']);
    $this->certificacoes['invalids'] = array(
        'data' => array_map(function ($v){
          return $v['certificacao'];
        },array_filter( $response, function ($v){
          return $v['situacao']<>'Ativa';
        } ))
    );  // certificacoes inativas
    $this->certificacoes['invalids']['lenght'] = count($this->certificacoes['invalids']['data']);
    $this->certificacoes['data'] = $response;  // response vira uma variavel dentro do contexto
    return $response;
  }

}
?>
