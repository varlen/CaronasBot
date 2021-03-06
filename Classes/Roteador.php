<?php
	require_once "Config.php";
	require_once "TelegramConnect.php";
	require_once "CaronaDAO.php";
	require_once "Carona.php";

	class Roteador{

		/*Espera o objeto 'message' já como array*/
		private static function processarDados($dados){
			$dadosProcessados = array();

			/*TODO inicializar objeto telegramConnect com dados da mensagem*/
			$dadosProcessados['username'] = $dados["message"]["from"]["username"];
			$dadosProcessados['chatId'] = $dados["message"]["chat"]["id"];
			$dadosProcessados['userId'] = $dados["message"]["from"]["id"];

			error_log( print_r( $dadosProcessados, true ) );

			return $dadosProcessados;
		}

		private static function processarComando($stringComando, &$args){
			/* Trata uma string que começa com '/', seguido por no maximo 32 numeros, letras ou '_', seguido ou não de '@nomeDoBot */
			$regexComando = '~^/(?P<comando>[\d\w_]{1,32})(?:@'. Config::getBotConfig('botName') .')?~';
			$comando = NULL;
			$args = NULL;

			if(preg_match($regexComando, $stringComando, $match)){
				$comando = $match['comando'];
				$stringComando = str_replace($match[0], "", $stringComando);
				$args = explode(" ", $stringComando);
			}

			error_log( print_r( $comando, true ) );
			error_log( print_r( $args, true ) );
			error_log( strlen($args[1]) );
			return $comando;
		}

		public static function direcionar($requestObj){
			$args = array();
			$comando = self::processarComando($requestObj['message']['text'], $args);
			$dados = self::processarDados($requestObj);

			$chat_id = $dados["chatId"];
			$user_id = $dados["userId"];
			$username = $dados['username'];
			
			/*Dividir cada comando em seu controlador*/
			if($username){
				$dao = new CaronaDAO();

				switch ($comando){
					/*comandos padrão*/
					case 'regras':
						$regras = "<b>Preencha o Formulário abaixo</b>
									Formulário: http://goo.gl/forms/OsTKcSLW2O

									Este documento descreve como o grupo costuma funcionar para não ficar muito bagunçado. São conselhos baseados no bom senso e experiência adquirida.

									-Nome e foto: libere a exibição do nome e foto no Telegram. Isso oferece mais segurança para os motoristas. Caso não exiba, existe grande chance de você ser removido por engano considerado inativo.

									-Horários: Ao oferecer carona para ir ao fundão, diga o horário que você pretende chegar no fundão. Ao oferecer carona para voltar ao Méier, diga o horário que você pretende sair do fundão.

									-Carona para o dia seguinte: espere um horário que não atrapalhe quem está  pedindo carona para voltar da faculdade. Sugestão: ofereça após as 19h

									-Valor: Não é pagamento, ninguém é obrigado a pagar como também ninguém é obrigado a dar carona. É uma ajuda de custos. O valor que a maioria doa é 3,50. Alguns 4, outros 3. Sugiro o valor de 3,50 por passageiro, independente se for 1 ou 5 no carro, pra não gerar concorrência desnecessária.

									-Não seja ganancioso, seu carro não é táxi.

									-Não seja mesquinho, você está indo para a  faculdade no conforto e rapidez, colabore com o motorista.

									-Ao oferecer ou pedir carona, utilize o verbo 'ir' se o sentido for meier-Fundão e o verbo 'voltar' se o sentido for fundao-Méier.

									-Participe ativamente do grupo: seja ativo e pegue ou ofereça caronas constantemente, caso contrário você estará tirando a vaga de alguém que pode precisar mais que você.

									-Se for removido: não fique chateado. Se foi algum equívoco, fale com algum admin e te colocam de volta.";

						TelegramConnect::sendMessage($chat_id, $regras);
						break;
					
					case 'help':
						$help = "Utilize este Bot para agendar as caronas. A utilização é super simples e através de comandos:

								/ida [horario] [vagas] [local] --> Este comando serve para definir um horário que você está INDO para o FUNDÃO. Ex: /ida 10:00 2 jardim
								Caso não seja colocado o parâmetro do horário (Ex: /ida) o bot irá apresentar a lista com as caronas registradas para o trajeto.

								/volta [horario] [vagas] [local] --> Este comando serve para definir um horário que você está VOLTANDO para o SEU BAIRRO. Ex: /volta 15:00 3 jardim
								Caso não seja colocado o parâmetro do horário (Ex: /volta) o bot irá apresentar a lista com as caronas registradas para o trajeto.

								/remover [ida/volta] --> Comando utilizado para remover a carona da lista. SEMPRE REMOVA a carona depois dela ter sido realizada. O sistema não faz isso automaticamente. Ex: /remover ida";
						
						TelegramConnect::sendMessage($chat_id, $help);
						break;
						
					case 'teste':
						error_log("teste");
						$texto = "Versão 1.0 - ChatId: $chat_id";

						TelegramConnect::sendMessage($chat_id, $texto);
						break;

					case 'stop':
						$texto = "Galera, olha a zoeira...";

						TelegramConnect::sendMessage($chat_id, $texto);
						break;

					case 'luiza':
						$texto = "Luis, me espera! Só vou atrasar uns minutinhos!";

						TelegramConnect::sendMessage($chat_id, $texto);
						break;

					/*Comandos de viagem*/
					case 'ida':
						if (count($args) == 1) {

							$resultado = $dao->getListaIda($chat_id);

							$texto = "<b>Ida para o Fundão</b>\n";
							foreach ($resultado as $carona){
								$texto .= (string)$carona . "\n";
							}

							TelegramConnect::sendMessage($chat_id, $texto);
						}
						elseif (count($args) == 4) {

							$horarioRaw = $args[1];
							$horarioRegex = '/^(?P<hora>[01]?\d|2[0-3])(?::(?P<minuto>[0-5]\d))?$/';

							$horarioValido = preg_match($horarioRegex, $horarioRaw, $resultado);

							$spots = $args[2];
							$location = $args[3];

							if ($horarioValido){
								$hora = $resultado['hora'];
								$minuto = isset($resultado['minuto']) ? $resultado['minuto'] : "00";

								$travel_hour = $hora . ":" . $minuto;
				
								$dao->adicionarIda($chat_id, $user_id, $username, $travel_hour, $spots, $location);

								TelegramConnect::sendMessage($chat_id, "@" . $username . " oferece carona de ida às " . $travel_hour . " com " . $spots . " vagas saindo de " . $location);
							} else{
								TelegramConnect::sendMessage($chat_id, "Horário inválido.");
							}
						} else {
							TelegramConnect::sendMessage($chat_id, "Uso: /ida [horario] [vagas] [local] \nEx: /ida 10:00 2 jardim");
						}
						break;

					case 'volta':
						if (count($args) == 1) {
							$resultado = $dao->getListaVolta($chat_id);

							$texto = "<b>Volta do Fundão</b>\n";
							foreach ($resultado as $carona){
								$texto .= (string)$carona . "\n";
							}

							TelegramConnect::sendMessage($chat_id, $texto);
						}
						elseif (count($args) == 4) {
							$horarioRaw = $args[1];
							$horarioRegex = '/^(?P<hora>[0-2]?\d)(:(?P<minuto>[0-5]\d))?$/';

							$horarioValido = preg_match($horarioRegex, $horarioRaw, $resultado);

							$spots = $args[2];
							$location = $args[3];

							if ($horarioValido){
								$hora = $resultado['hora'];
								$minuto = isset($resultado['minuto']) ? $resultado['minuto'] : "00";

								$travel_hour = $hora . ":" . $minuto;

								$dao->adicionarVolta($chat_id, $user_id, $username, $travel_hour, $spots, $location);

								TelegramConnect::sendMessage($chat_id, "@" . $username . " oferece carona de volta às " . $travel_hour . " com " . $spots . " vagas indo até " . $location);

							}else{
								TelegramConnect::sendMessage($chat_id, "Horário inválido.");
							}
						} else {
							TelegramConnect::sendMessage($chat_id, "Uso: /volta [horario] [vagas] [local] \nEx: /volta 15:00 2 jardim");
						}
						break;

					case 'remover':
						if($args[1] == 'ida'){
							$dao->removerIda($chat_id, $user_id);

							TelegramConnect::sendMessage($chat_id, "Sua ida foi removida");
						}
						elseif ($args[1] == 'volta'){
							$dao->removerVolta($chat_id, $user_id);

							TelegramConnect::sendMessage($chat_id, "Sua volta foi removida");

						} else {
							TelegramConnect::sendMessage($chat_id, "Formato: /remover [ida|volta]");
						}

						break;
				}
			}else{
				TelegramConnect::sendMessage($chat_id, "Registre seu username nas configurações do Telegram para utilizar o Bot.");
			}
		}
	}
