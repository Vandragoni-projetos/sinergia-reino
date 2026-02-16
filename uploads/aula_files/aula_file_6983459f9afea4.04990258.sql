-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 31/01/2026 às 14:50
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u527060234_gatewaypro1`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `alunos_acessos`
--

CREATE TABLE `alunos_acessos` (
  `id` int(11) NOT NULL,
  `aluno_email` varchar(255) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `oferta_id` int(11) DEFAULT NULL COMMENT 'ID da oferta que gerou este acesso (NULL = preço padrão do produto)',
  `data_concessao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_expiracao` timestamp NULL DEFAULT NULL COMMENT 'Data de expiração do acesso. NULL = acesso vitalício',
  `criado_manualmente` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Acesso criado manualmente pelo infoprodutor, 0 = Acesso via compra'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `alunos_acessos`
--

INSERT INTO `alunos_acessos` (`id`, `aluno_email`, `produto_id`, `oferta_id`, `data_concessao`, `data_expiracao`, `criado_manualmente`) VALUES
(34, 'hojeitocerto@gmail.com', 42, NULL, '2026-01-24 23:24:44', NULL, 0),
(35, 'gildenefsouza@gmail.com', 42, NULL, '2026-01-31 03:55:43', NULL, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `aluno_progresso`
--

CREATE TABLE `aluno_progresso` (
  `id` int(11) NOT NULL,
  `aluno_email` varchar(255) NOT NULL,
  `aula_id` int(11) NOT NULL,
  `data_conclusao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `aluno_progresso`
--

INSERT INTO `aluno_progresso` (`id`, `aluno_email`, `aula_id`, `data_conclusao`) VALUES
(12, 'hojeitocerto@gmail.com', 16, '2026-01-24 23:39:12'),
(13, 'hojeitocerto@gmail.com', 17, '2026-01-24 23:39:25'),
(14, 'hojeitocerto@gmail.com', 18, '2026-01-24 23:40:11'),
(15, 'hojeitocerto@gmail.com', 20, '2026-01-30 22:50:11'),
(16, 'hojeitocerto@gmail.com', 19, '2026-01-30 22:50:38'),
(17, 'hojeitocerto@gmail.com', 15, '2026-01-30 22:51:00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `aulas`
--

CREATE TABLE `aulas` (
  `id` int(11) NOT NULL,
  `modulo_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `url_video` varchar(255) DEFAULT NULL COMMENT 'URL do vídeo (YouTube, Vimeo, etc.), pode ser NULL',
  `descricao` text DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `release_days` int(11) NOT NULL DEFAULT 0 COMMENT 'Número de dias após a compra para a aula ser liberada',
  `tipo_conteudo` enum('video','files','mixed') NOT NULL DEFAULT 'video' COMMENT 'Tipo de conteúdo da aula: video, files ou mixed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `aulas`
--

INSERT INTO `aulas` (`id`, `modulo_id`, `titulo`, `url_video`, `descricao`, `ordem`, `release_days`, `tipo_conteudo`) VALUES
(15, 18, 'Aula 01 - Preparando o Domínio', 'https://youtu.be/2Q2DmjHK0zg', 'Seja bem-vindo(a) ao treinamento de checkout próprio. Nesta primeira aula, você dá o passo inicial para ter controle total sobre suas vendas: a aquisição do seu próprio domínio.\r\n\r\nVocê vai entender por que ter um checkout próprio é uma decisão estratégica, com benefícios como:\r\n\r\nControle total da operação\r\n\r\nSegurança no recebimento\r\n\r\nTaxas muito mais baixas\r\n\r\nRecebimento imediato das vendas, inclusive no cartão de crédito\r\n\r\nNa parte prática da aula, mostramos passo a passo como comprar um domínio do zero, utilizando a Hostinger, desde a escolha do nome até a confirmação do pagamento e ativação do domínio.\r\n\r\n📌 O que você aprende nesta aula:\r\n\r\nO que é um domínio e por que ele é essencial para seu checkout próprio\r\n\r\nComo escolher um bom domínio para sua marca\r\n\r\nOnde comprar domínio de forma barata e confiável\r\n\r\nComo verificar domínios disponíveis\r\n\r\nProcesso completo de compra e pagamento\r\n\r\nAtivação inicial do domínio após a compra\r\n\r\nCuidados com validações e confirmações de cadastro\r\n\r\nAo final da aula, você já terá seu domínio pronto para uso, preparado para ser conectado ao servidor na próxima etapa do treinamento.\r\n\r\n➡️ Na próxima aula, vamos configurar a hospedagem e conectar o domínio ao servidor.', 0, 0, 'video'),
(16, 18, 'Aula 02 - Preparando Hospedagem', 'https://youtu.be/fB5_CIJ7mis', 'Nesta segunda aula do treinamento, vamos avançar para a configuração da hospedagem, preparando o ambiente onde o seu checkout próprio irá rodar.\r\n\r\nApós a compra do domínio na aula anterior, agora você aprende como contratar uma hospedagem confiável, econômica e com bom desempenho, além de realizar a conexão correta entre domínio e servidor.\r\n\r\nDurante a aula, é mostrado passo a passo como contratar uma hospedagem na Val e Roxa, acessar o painel do cliente e localizar as informações necessárias para apontar o domínio corretamente.\r\n\r\n📌 O que você aprende nesta aula:\r\n\r\nO que é hospedagem e por que ela é essencial para o checkout próprio\r\n\r\nComo escolher uma hospedagem simples e suficiente para iniciar\r\n\r\nContratação prática da hospedagem\r\n\r\nComo acessar a área do cliente da hospedagem\r\n\r\nOnde encontrar os servidores de nome (NameServers / DNS)\r\n\r\nComo apontar o domínio para a hospedagem\r\n\r\nO que é propagação de domínio\r\n\r\nComo verificar a propagação usando o site dnschecker.org\r\n\r\nAo final da aula, o seu domínio estará apontado para a hospedagem, restando apenas aguardar a propagação para dar sequência à instalação do checkout próprio.\r\n\r\n➡️ Na próxima aula, vamos iniciar a instalação do checkout próprio no servidor.', 0, 0, 'video'),
(17, 18, 'Aula 03 - Instalando a Plataforma', 'https://youtu.be/stBeMcUPYyY', 'Nesta aula, vamos partir para a instalação prática da plataforma no servidor. Após a propagação do domínio e a configuração da hospedagem, chegou o momento de colocar o sistema no ar.\r\n\r\nVocê vai acompanhar todo o processo passo a passo, desde a verificação da propagação do domínio até o acesso final ao painel administrativo da plataforma.\r\n\r\n📌 O que você aprende nesta aula:\r\n\r\nComo verificar se o domínio já propagou corretamente\r\n\r\nComo acessar o painel DirectAdmin da hospedagem\r\n\r\nLimpeza do diretório public_html\r\n\r\nUpload e extração do código-fonte da plataforma\r\n\r\nLocalização e edição do arquivo de configuração (config.php)\r\n\r\nCriação do banco de dados no DirectAdmin\r\n\r\nConfiguração de usuário e senha do banco de dados\r\n\r\nImportação do banco de dados via phpMyAdmin\r\n\r\nTeste da instalação pelo domínio\r\n\r\nPrimeiro acesso ao painel administrativo\r\n\r\nUso das credenciais iniciais de super admin\r\n\r\nAlteração de dados e senha do administrador por segurança\r\n\r\nAo final da aula, a plataforma estará totalmente instalada e funcionando, com acesso ao painel de administração e pronta para as próximas configurações.\r\n\r\n➡️ Nas próximas aulas, você vai aprender a utilizar o painel administrativo, o painel do infoprodutor e a configurar integrações do sistema.\r\n\r\nLink do código fonte:\r\nhttps://drive.google.com/drive/folders/17REB79QzIDwqcPYrcj0XRgmoZGIdFGh_', 0, 0, 'video'),
(18, 18, 'Aula 04 - Configurando SMTP Email', 'https://youtu.be/_vTVjHZtPrQ', 'Nesta aula, vamos realizar uma configuração essencial para o funcionamento da plataforma: o SMTP de e-mail. Essa configuração é responsável por garantir que todos os e-mails enviados pelo sistema cheguem corretamente aos usuários.\r\n\r\nVocê vai aprender a configurar o e-mail que será usado para enviar:\r\n\r\nInformações de acesso à plataforma\r\n\r\nDados de cursos adquiridos\r\n\r\nConfirmações de compra\r\n\r\nComunicações automáticas do sistema\r\n\r\nTudo isso utilizando um e-mail profissional criado na própria hospedagem.\r\n\r\n📌 O que você aprende nesta aula:\r\n\r\nO que é SMTP e qual sua função na plataforma.\r\n\r\nOnde configurar o SMTP dentro do painel administrativo.\r\n\r\nComo criar uma conta de e-mail profissional na hospedagem.\r\n\r\nDefinição de usuário e senha do e-mail\r\n\r\nConfiguração do servidor SMTP, porta e criptografia.\r\n\r\nComo definir o nome e e-mail do remetente.\r\n\r\nAjuste da URL de login da área de membros nos e-mails\r\n\r\nComo testar a conexão SMTP\r\n\r\nComo enviar um e-mail de teste para validar a configuração.\r\n\r\nAo final da aula, o sistema estará enviando e-mails corretamente, garantindo que seus usuários recebam todas as informações importantes após uma compra ou cadastro.', 0, 0, 'video'),
(19, 18, 'Aula 05 - Recuperação de Carrinho e E-mail Marketing (Melhorias)', 'https://youtu.be/4ffDYBZynfg', 'Nesta aula, vamos configurar duas funcionalidades novas e muito importantes da plataforma: a recuperação automática de carrinho e o sistema de e-mail marketing.\r\n\r\nEsses recursos permitem aumentar suas conversões e manter um relacionamento ativo com seus usuários de forma automática e segura, evitando perda de vendas e problemas com reputação de e-mail.\r\n\r\n📌 O que você aprende nesta aula:\r\n\r\nComo funciona a recuperação automática de carrinho\r\n\r\nOnde configurar a recuperação de carrinho no painel administrativo\r\n\r\nO que é um Cron Job e por que ele é necessário\r\n\r\nCriação e configuração de Cron Job para recuperação de carrinho\r\n\r\nDefinição do intervalo ideal de execução (ex: a cada 10 minutos)\r\n\r\nComo o sistema identifica usuários que geraram pagamento e não concluíram\r\n\r\nEnvio automático de e-mails de recuperação de carrinho\r\n\r\nAlém disso, você aprende a configurar o sistema de e-mail marketing, incluindo:\r\n\r\nCriação de Cron Job específico para e-mail marketing\r\n\r\nConfiguração de intervalo de envio (ex: a cada 2 minutos)\r\n\r\nComo funcionam as filas de envio de e-mails\r\n\r\nEnvio controlado de mensagens em lotes (30 e-mails por ciclo)\r\n\r\nBoas práticas para evitar spam e queda de reputação do e-mail\r\n\r\nEnvio de campanhas para infoprodutores, clientes ou ambos\r\n\r\nVerificação e reativação do Cron Job antes de disparar campanhas\r\n\r\nAo final da aula, o sistema estará totalmente configurado para recuperar vendas automaticamente e enviar campanhas de e-mail de forma segura e escalável.', 0, 0, 'video'),
(20, 19, 'Aula 01 - Conhecendo o Painel ADM', 'https://youtu.be/kfmiSCpeEwQ', 'Nesta aula, você vai conhecer em detalhes o Painel Administrativo da plataforma, entendendo como ele funciona e qual é o seu papel na gestão geral do sistema.\r\n\r\nAntes de aprofundar nas configurações de produtos e vendas, é fundamental compreender a diferença entre os dois painéis existentes:\r\n\r\nPainel do Administrador (gestão total da plataforma)\r\n\r\nPainel do Infoprodutor (cadastro de produtos, integrações e vendas)\r\n\r\nO foco desta aula é explorar todas as funcionalidades disponíveis no painel ADM, apresentando cada seção e suas responsabilidades.\r\n\r\n📌 O que você aprende nesta aula:\r\n\r\nDiferença entre painel administrativo e painel do infoprodutor\r\n\r\nVisão geral da Dashboard do Administrador\r\n\r\nAcompanhamento das informações gerais da plataforma\r\n\r\nGerenciamento de usuários da plataforma\r\n\r\nGerenciamento de infoprodutores\r\n\r\nVisualização de clientes finais dos infoprodutores\r\n\r\nAcesso a relatórios detalhados\r\n\r\nRevisão das configurações SMTP\r\n\r\nPersonalização da plataforma:\r\n\r\nCor primária\r\n\r\nLogo principal do sistema\r\n\r\nNome da plataforma\r\n\r\nLogo específica do checkout\r\n\r\nImagem da tela de login\r\n\r\nÍcone da plataforma (favicon)\r\n\r\nEntendimento da área de revenda autorizada\r\n\r\nCriação prática de um novo usuário infoprodutor\r\n\r\nDefinição de login e senha do infoprodutor\r\n\r\nAo final da aula, você terá uma visão completa do painel administrativo, entendendo como gerenciar usuários, personalizar a plataforma e preparar o ambiente para os próximos passos.\r\n\r\n➡️ Na próxima aula, vamos conhecer em profundidade o painel do infoprodutor, onde são cadastrados produtos, integrações de pagamento e demais configurações de vendas.', 0, 0, 'video'),
(21, 19, 'Aula 02 - Conhecendo o Painel do Infoprodutor', 'https://youtu.be/XITzXJIkiJY', 'Nesta aula, você vai conhecer em profundidade o Painel do Infoprodutor, o ambiente onde são criados os produtos, configurados os métodos de pagamento, personalizados os checkouts e estruturada a área de membros.\r\n\r\nApós entender o painel administrativo na aula anterior, agora é o momento de explorar o painel responsável pela operação direta de vendas e entrega de conteúdos.\r\n\r\n📌 O que você aprende nesta aula:\r\n\r\nComo acessar o painel do infoprodutor utilizando a conta criada no painel ADM\r\n\r\nVisão geral da Dashboard do Infoprodutor\r\n\r\nAcompanhamento de vendas e relatórios\r\n\r\nCriação do primeiro produto digital\r\n\r\nDefinição de preço, descrição e imagem de capa\r\n\r\nConfiguração do método de entrega:\r\n\r\nLink externo\r\n\r\nArquivo (PDF)\r\n\r\nÁrea de membros interna\r\n\r\nEdição das informações gerais do produto\r\n\r\nConfiguração de Order Bump\r\n\r\nDefinição dos métodos de pagamento:\r\n\r\nPix\r\n\r\nCartão de crédito\r\n\r\nIntegração com gateways\r\n\r\nConfiguração de rastreamento e pixels:\r\n\r\nPixel do Facebook\r\n\r\nGoogle Analytics\r\n\r\nTokens de conversão\r\n\r\nPersonalização completa do checkout:\r\n\r\nNome do produto\r\n\r\nAparência e cores\r\n\r\nBanners\r\n\r\nCampos do cliente (CPF, telefone)\r\n\r\nCronômetro e notificações\r\n\r\nRedirecionamento pós-compra\r\n\r\nGeração e visualização do link do checkout\r\n\r\nAlém disso, você aprende a trabalhar com a área de membros, incluindo:\r\n\r\nCriação automática da área de membros para o produto\r\n\r\nGerenciamento e personalização da área de membros\r\n\r\nCriação de módulos\r\n\r\nCriação e edição de aulas\r\n\r\nInserção de vídeos do YouTube\r\n\r\nUpload de materiais de apoio\r\n\r\nPré-visualização da área de membros como aluno\r\n\r\nAo final da aula, você terá um produto completamente configurado, com checkout funcional e área de membros estruturada, pronta para receber conteúdos e alunos.\r\n\r\n➡️ Nas próximas aulas, você aprenderá a configurar integrações, webhooks, gateways de pagamento e recursos avançados da plataforma.', 0, 0, 'video'),
(22, 19, 'Aula 03 - Modo SAAS (Melhorias)', 'https://youtu.be/PizboytA5V8', 'Nesta aula, você vai conhecer uma atualização importante da plataforma: o Modo SaaS. Esse recurso permite transformar a sua plataforma em um sistema no modelo SaaS, onde outros infoprodutores podem se cadastrar, contratar planos e utilizar a tecnologia mediante assinatura.\r\n\r\nO foco da aula é mostrar como ativar, configurar e gerenciar esse modo diretamente pelo painel administrativo.\r\n\r\n📌 O que você aprende nesta aula:\r\n\r\nO que é o Modo SaaS e para que ele serve\r\n\r\nOnde encontrar a opção de Modo SaaS no painel administrativo\r\n\r\nComo habilitar o Modo SaaS\r\n\r\nLiberação do botão de registro automático na tela de login\r\n\r\nCadastro automático de novos infoprodutores\r\n\r\nComo cobrar mensalidade dos infoprodutores pelo uso da plataforma\r\n\r\nCriação e configuração de planos (Free e pagos)\r\n\r\nDefinição de limites por plano:\r\n\r\nQuantidade máxima de produtos\r\n\r\nQuantidade máxima de pedidos por mês\r\n\r\nConfiguração de plano gratuito ou obrigatório\r\n\r\nDefinição de preços dos planos\r\n\r\nConfiguração do gateway de pagamento para assinaturas\r\n\r\nEscolha do gateway para Pix e cartão de crédito\r\n\r\nVisualização das assinaturas ativas na plataforma\r\n\r\nGerenciamento manual de assinaturas\r\n\r\nAlteração de planos de infoprodutores já cadastrados\r\n\r\nAo final da aula, você entenderá como utilizar o Modo SaaS para monetizar sua plataforma, oferecendo acesso por assinatura, com total controle sobre planos, limites e pagamentos.\r\n\r\n➡️ Esse recurso é ideal para quem deseja escalar o negócio, oferecendo a plataforma como serviço para outros infoprodutores.', 0, 0, 'video'),
(23, 20, 'Aula 01 - Integrando com a Pushinpay', 'https://youtu.be/IAjEpvOKzX4', 'Nesta aula, iniciamos o Módulo de Integrações, configurando um dos principais gateways de pagamento da plataforma: a PushinPay.\r\n\r\nVocê vai aprender como realizar a integração passo a passo, conectando o gateway ao sistema para possibilitar recebimento de pagamentos via Pix, garantindo segurança, automação e funcionamento correto dos pedidos.\r\n\r\n📌 O que você aprende nesta aula:\r\n\r\nO que é a PushinPay e como ela funciona\r\n\r\nOnde acessar a integração de gateways no painel administrativo\r\n\r\nComo criar ou acessar uma conta na PushinPay\r\n\r\nObtenção das credenciais necessárias para integração\r\n\r\nConfiguração da PushinPay dentro da plataforma\r\n\r\nDefinição do gateway para pagamentos via Pix\r\n\r\nTestes de funcionamento da integração\r\n\r\nValidação do recebimento automático de pedidos\r\n\r\nBoas práticas para garantir estabilidade e segurança na integração\r\n\r\nAo final da aula, o gateway PushinPay estará totalmente integrado, permitindo que os infoprodutores recebam pagamentos via Pix diretamente pela plataforma.\r\n\r\n➡️ Nas próximas aulas, você aprenderá a integrar outros gateways de pagamento e a configurar opções adicionais como cartão de crédito, webhooks e automações.', 0, 0, 'video'),
(24, 20, 'Aula 02 - Integrando com Mercado Pago', 'https://youtu.be/IAjEpvOKzX4', 'Nesta aula, vamos configurar a integração da plataforma com o Mercado Pago, habilitando pagamentos via cartão de crédito, boleto e Pix, ampliando as opções de pagamento disponíveis no checkout.\r\n\r\nVocê vai acompanhar todo o processo prático, desde a criação da aplicação no painel do Mercado Pago até a inserção das credenciais corretas no painel administrativo da plataforma.\r\n\r\n📌 O que você aprende nesta aula:\r\n\r\nOnde acessar as integrações de gateway de pagamento na plataforma\r\n\r\nComo selecionar o Mercado Pago como gateway\r\n\r\nAcesso ao painel do Mercado Pago\r\n\r\nVerificação da conta do Mercado Pago\r\n\r\nCriação de uma nova aplicação\r\n\r\nDefinição do nome da aplicação\r\n\r\nEscolha do tipo de integração (pagamentos online / desenvolvimento próprio)\r\n\r\nConfiguração do modelo de checkout\r\n\r\nAutorização da aplicação\r\n\r\nAtivação das credenciais de produção\r\n\r\nConfiguração do setor e URL do site\r\n\r\nObtenção da Public Key\r\n\r\nObtenção do Access Token\r\n\r\nInserção das credenciais no painel administrativo\r\n\r\nSalvamento e validação da integração\r\n\r\nAo final da aula, a plataforma estará totalmente integrada ao Mercado Pago, permitindo o recebimento de pagamentos via cartão de crédito, boleto e Pix, funcionando em conjunto com a PushinPay.\r\n\r\n➡️ Com isso, o checkout passa a oferecer múltiplas formas de pagamento, aumentando as conversões e a flexibilidade para os clientes.', 0, 0, 'video'),
(25, 20, 'Aula 03 - Integrando com Efí (Melhorias)', 'https://youtu.be/NPrAvgGhtPE', 'Nesta aula, vamos realizar a integração da plataforma com o gateway Efí, um dos meios de pagamento mais completos e utilizados, permitindo o recebimento tanto via Pix quanto cartão de crédito.\r\n\r\nVocê vai aprender todo o processo prático, desde a criação da conta na Efí até a configuração final no painel administrativo da plataforma, utilizando API, certificados e credenciais oficiais.\r\n\r\n📌 O que você aprende nesta aula:\r\n\r\nO que é o gateway Efí e por que utilizá-lo\r\n\r\nCriação de conta gratuita na Efí (CPF ou CNPJ)\r\n\r\nAcesso ao painel da Efí\r\n\r\nCriação de uma chave Pix\r\n\r\nAcesso à área de API\r\n\r\nCriação de uma nova aplicação\r\n\r\nSeleção da API de cobranças\r\n\r\nHabilitação de permissões em ambiente de produção e homologação\r\n\r\nAutenticação e finalização da criação da aplicação\r\n\r\nObtenção do Client ID\r\n\r\nObtenção do Client Secret\r\n\r\nCriação e download do certificado digital (.p12)\r\n\r\nUpload do certificado no painel da plataforma\r\n\r\nLocalização do identificador de conta\r\n\r\nConfiguração da chave Pix\r\n\r\nSalvamento e validação da integração\r\n\r\nAo final da aula, a plataforma estará totalmente integrada ao gateway Efí, permitindo o recebimento de pagamentos via Pix e cartão de crédito, ampliando ainda mais as opções de pagamento disponíveis no checkout.\r\n\r\n➡️ Com essa integração, você passa a ter mais flexibilidade e controle sobre os recebimentos, utilizando um gateway robusto e confiável.', 0, 0, 'video'),
(26, 20, 'Aula 04 - Integrando com HyperCash (Melhorias)', 'https://youtu.be/LKzRMsBJ2h0', '', 0, 0, 'video'),
(27, 21, 'Aula 01 - Como Atualizar', 'https://youtu.be/I6PZJSXt9cs', 'Fala pessoal, estamos de volta aqui para falar sobre atualizações. Antes de eu te mostrar como é que você vai atualizar o seu sistema, eu preciso te explicar como é que vai funcionar, como é que funciona cada atualização. Eu tô aqui na minha área de membros, ó. Eu vou clicar aqui em atualizações e vejo que a atualização que tem de agora é a atualização V2, tá? É a versão V2.\r\nEntão você só vai precisar instalar se você não tiver essa versão aqui no código fonte, ó. Deixa eu ver na instalação, nessa área aqui de instalando a plataforma aqui, onde fica o código fonte, ele sempre vai tá atualizado com a nova versão, tá? Se você comprou, se você adquiriu o checkout depois da nova atualização, então o seu sistema já tá atualizado.\r\nMas como é que eu posso confirmar se tá atualizado ou não? É o seguinte, você vai entrar lá no seu painel administrativo, já tô com ele aberto aqui, ó, no painel administrativo. Se você rolar aqui no site de bar para baixo, ó, vai aparecer aqui versão da plataforma, nesse caso, tá? V2, tá? V2.0.0. Significa que já tá atualizado com essa versão aqui, ó, com a versão V2, tá? Já tá atualizado, não precisa atualizar.\r\nSe o seu não tiver aparecendo nada aqui, ó, não tiver aparecendo a versão, significa que você tem que atualizar. Ou se você tiver numa versão aqui anterior, a V2, por exemplo, 1.5, significa que você tem que atualizar também. Outra coisa muito importante que o pessoal tem muita dúvida, quando você atualizar sua plataforma, você vai perder as configurações, vai perder seus dados? Não, pessoal, a atualização, ela é só uma atualização da sua plataforma.\r\nvocê não vai perder nada do que você já fez, tá? Dito isso, como é que a gente vai atualizar a plataforma? Bom, você vai receber sempre aqui na atualização esse link aqui, tá, da atualização aqui, tá? O código fonte da atualização e o banco de dados. Em alguns casos, pessoal, vai vir esses dois arquivos aqui, ó, o ponto zip, que é o código fonte da atualização, e o ponto Sqrl, que é o atualização do banco de dados.\r\nEm alguns casos não vai precisar, não vai vir esse arquivo aqui do banco de dados. Em alguns casos, somente com o arquivo já vai dar para atualizar. Beleza? Mas vamos lá. Como é que a gente vai instalar aqui essa atualização? Lembra aqui do direct admin da sua hospedagem? Então, primeira coisa que a gente tem que fazer aqui, como aqui, ó, eu já vi que tem SQL, tá? Atualização de banco de dados, então vamos aqui, primeiramente no banco de dados.\r\nEu vou clicar aqui no banco de dados. Eu vou vir aqui, ó, no meu banco de dados, gerenciar e vou clicar em PHP My Admin. Pronto. Basta eu clicar aqui no meu banco de dados. E agora eu vou clicar aqui em importar, escolher arquivo. E eu vou buscar aquele arquivo que eu baixei, lembra? Tá aqui, ó.\r\nAqui tá a pasta do arquivo lá que eu baixei de atualização. E a gente vai subir esse aqui, ó. Update V2. que é o banco de dados e clicar em importar. Pronto, o banco de dados importado com sucesso. Quer dizer que já foi atualizado o banco de dados. Eu posso fechar aqui. Agora a gente vai voltar e vai atualizar o código fonte. Então aqui dentro do seu sistema do código fonte aqui na hospedagem é onde você vai jogar sua nova atualização.\r\nMas antes de mais nada é muito importante que você faça um backup, tá? Faça um backup por segurança. Eu sempre costumo fazer um backup. Basta clicar aqui, ó, para selecionar todos os arquivos. Eu venho aqui em arquivar e eu boto aqui backup. Beleza? Outra coisa muito importante, pessoal, você vem aqui na sua pasta config, você dá dois cliques aqui no config.\r\nphp, lembra que é onde você configura seu banco de dados e você vai fazer um backup dessa parte aqui, ó, das suas configurações do banco de dados, porque pode ser que na atualização mude esse arquivo config.php e sobrescreva o que você já tem aqui, tá? Tá? Então eu copiei aqui e a e eu vou deixar aqui o meu bloco de notas por enquanto.\r\nBeleza? Posso fechar agora? Sim, no public html eu venho em upload e a gente vai subir aqui essa nova atualização. Update ativ 2. Posso iniciar upload. Beleza, feito. Agora a gente vai procurar ele aqui e vai extrair, ó. Ped, clica com o botão direito, extrair. Posso extrair? Pronto. Agora é só voltar aqui na na pasta config config.\r\nphp PHP e vou colar novamente. Ó, você viu que sobescreveu aqui, vem aqui, cola minhas configurações e salvar arquivo. Pronto, tá atualizado com sucesso. Basicamente é sempre isso que você vai fazer para atualizar. Outra coisa aqui que eu esqueci de falar para vocês, vamos voltar aqui no banco de dados. PHP aqui na demo.\r\nÉ sempre importante você fazer um backup também do seu banco de dados, tá? Antes de você atualizar, basta você clicar aqui em exportar, exportar e você pode salvar o arquivo, beleza?', 0, 0, 'video'),
(28, 21, 'Aula 02 - Atualizaçao Hotfix V2.2.1 (12/01/2026)', '', 'Atualização Hotfix V2.2.1 (12/01/2026)\r\n\r\nArquivos da Atualização:\r\nhttps://drive.google.com/drive/folders/1ufAsp_q9ozgsdu7f13wDO4BWeIl9cw1U?usp=sharing\r\n\r\nO que Muda?\r\n- Correção de alguns bugs encontrados\r\n\r\nAtenção!!!\r\nJá estamos implementando os pedidos de novas atualizações do formulário.', 0, 0, 'files'),
(29, 22, 'Atualizações e Correções', '', 'Ajude Dando Dicas de Novas Funcionalidades ou Possíveis Bugs Encontrados.\r\nhttps://forms.gle/7WNvS9QJSUUbPWtC7', 0, 0, 'files'),
(30, 23, 'Grupo Whatsapp', '', 'Envie seu convite, em breve estaremos aceitando todos.\r\nhttps://chat.whatsapp.com/DjbxaNrNxo75I6tU18iXJJ', 0, 0, 'files'),
(31, 24, 'Instalação Profissional', '', 'Quer que a nossa equipe instale pra você e deixe tudo pronto?\r\nhttps://getfy.online/checkout?p=5c6cb757c8e8dc6ebf38f6196a973e33', 0, 0, 'files');

-- --------------------------------------------------------

--
-- Estrutura para tabela `aula_arquivos`
--

CREATE TABLE `aula_arquivos` (
  `id` int(11) NOT NULL,
  `aula_id` int(11) NOT NULL,
  `nome_original` varchar(255) NOT NULL COMMENT 'Nome original do arquivo',
  `nome_salvo` varchar(255) NOT NULL COMMENT 'Nome do arquivo salvo no servidor',
  `caminho_arquivo` varchar(255) NOT NULL COMMENT 'Caminho completo do arquivo no servidor (ex: uploads/aula_files/arquivo.pdf)',
  `tipo_mime` varchar(100) DEFAULT NULL COMMENT 'Tipo MIME do arquivo (ex: application/pdf, image/png)',
  `tamanho_bytes` int(11) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição do arquivo dentro da aula',
  `data_upload` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `aula_arquivos`
--

INSERT INTO `aula_arquivos` (`id`, `aula_id`, `nome_original`, `nome_salvo`, `caminho_arquivo`, `tipo_mime`, `tamanho_bytes`, `ordem`, `data_upload`) VALUES
(9, 28, 'Banners Checkout 712x1080 Getfy SinergiaClub_01.png', 'aula_file_697539ac776cc5.40909722.png', 'uploads/aula_files/aula_file_697539ac776cc5.40909722.png', 'image/png', 704111, 0, '2026-01-24 21:29:16'),
(10, 29, 'Banners Checkout 712x1080 Getfy SinergiaClub_01.png', 'aula_file_697539cd09fb64.59213925.png', 'uploads/aula_files/aula_file_697539cd09fb64.59213925.png', 'image/png', 704111, 0, '2026-01-24 21:29:49'),
(11, 30, 'Banners Checkout 712x1080 Getfy SinergiaClub_01.png', 'aula_file_69753a00300ad0.86687633.png', 'uploads/aula_files/aula_file_69753a00300ad0.86687633.png', 'image/png', 704111, 0, '2026-01-24 21:30:40'),
(12, 31, 'Banners Checkout 712x1080 Getfy SinergiaClub_01.png', 'aula_file_69753a2626e685.06068519.png', 'uploads/aula_files/aula_file_69753a2626e685.06068519.png', 'image/png', 704111, 0, '2026-01-24 21:31:18');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cloned_sites`
--

CREATE TABLE `cloned_sites` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor dono do site clonado',
  `original_url` varchar(2048) NOT NULL COMMENT 'URL do site original que foi clonado',
  `title` varchar(255) DEFAULT NULL COMMENT 'Título da página clonada',
  `original_html` longtext NOT NULL COMMENT 'Conteúdo HTML original da página clonada',
  `edited_html` longtext DEFAULT NULL COMMENT 'Conteúdo HTML da página após edição do usuário',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `slug` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cloned_site_settings`
--

CREATE TABLE `cloned_site_settings` (
  `id` int(11) NOT NULL,
  `cloned_site_id` int(11) NOT NULL COMMENT 'ID do site clonado associado',
  `facebook_pixel_id` varchar(255) DEFAULT NULL COMMENT 'ID do Facebook Pixel',
  `google_analytics_id` varchar(255) DEFAULT NULL COMMENT 'ID do Google Analytics',
  `custom_head_scripts` longtext DEFAULT NULL COMMENT 'Scripts personalizados a serem injetados no <head>',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes`
--

CREATE TABLE `configuracoes` (
  `chave` varchar(255) NOT NULL,
  `valor` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `configuracoes`
--

INSERT INTO `configuracoes` (`chave`, `valor`) VALUES
('email_template_delivery_html', '<!DOCTYPE html>\n<html lang=\"pt-br\">\n<head>\n    <meta charset=\"UTF-8\">\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n    <meta http-equiv=\"X-UA-Compatible\" content=\"ie=edge\">\n    <title>Bem-vindo(a)!</title>\n    <style>\n        @import url(\'https://www.google.com/url?sa=E&source=gmail&q=https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700%26display=swap\');\n        /* Estilos para responsividade */\n        @media screen and (max-width: 600px) {\n            .container {\n                width: 100% !important;\n                padding: 10px !important;\n            }\n            .content {\n                padding: 25px 20px !important;\n            }\n            .header-img {\n                width: 150px !important;\n            }\n            h1 {\n                font-size: 24px !important;\n            }\n        }\n    </style>\n</head>\n<body style=\"margin: 0; padding: 0; background-color: #f1f5f9; font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;\">\n    <!-- Preheader (texto de visualização no cliente de e-mail) -->\n    <div style=\"display: none; max-height: 0; overflow: hidden;\">Tudo pronto! Seu acesso aos produtos já está disponível.</div>\n    <table align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"border-collapse: collapse;\">\n        <tr>\n            <td align=\"center\" style=\"padding: 20px 0;\">\n                <table class=\"container\" align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"600\" style=\"border-collapse: collapse; background-color: #ffffff; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e2e8f0;\">\n                    <!-- Cabeçalho com a Nova Logo -->\n                    <tr>\n                        <td align=\"center\" bgcolor=\"#1e1e2f\" style=\"padding: 30px 20px; background-color: #1e1e2f;\">\n                            <div>\n                                <img class=\"header-img\" src=\"https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-gatewaypro.png\" alt=\"Logo GarewayPro\" width=\"200\" style=\"display: block; border: 0;\" />\n                            </div>\n                        </td>\n                    </tr>\n                    <!-- Corpo Principal -->\n                    <tr>\n                        <td class=\"content\" style=\"padding: 40px 35px;\">\n                            <h1 style=\"font-size: 28px; font-weight: 700; color: #0f172a; margin: 0 0 15px 0;\">Parabéns, {CLIENT_NAME}!</h1>\n                            <p style=\"margin: 0 0 25px 0; font-size: 16px; line-height: 1.6; color: #475569;\">\n                                Seus produtos adquiridos foram liberados com sucesso! Abaixo estão os detalhes de acesso para cada um deles:\n                            </p>\n                            <!-- Início do Loop de Produtos -->\n                            <!-- LOOP_PRODUCTS_START -->\n                            <div style=\"background-color: #ffffff; border: 1px solid #2DD05E; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.03);\">\n                                <h2 style=\"font-size: 20px; font-weight: 600; color: #1e293b; margin: 0 0 15px 0;\">{PRODUCT_NAME}</h2>\n                                \n                                <!-- Bloco para Área de Membros -->\n                                <!-- IF_PRODUCT_TYPE_MEMBER_AREA -->\n                                <p style=\"margin: 0 0 10px 0; font-size: 15px; color: #475569;\">Este produto está disponível em sua área de membros.</p>\n                                <p style=\"margin: 0 0 5px 0; font-size: 15px; color: #475569;\"><strong>Seu login:</strong> {CLIENT_EMAIL}</p>\n                                <p style=\"margin: 0 0 20px 0; font-size: 15px; color: #475569;\"><strong>Sua senha:</strong> {MEMBER_AREA_PASSWORD}</p>\n                                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"border-collapse: collapse;\">\n                                    <tr>\n                                        <td align=\"center\" style=\"background-color: #2DD05E; border-radius: 8px;\">\n                                            <a href=\"{MEMBER_AREA_LOGIN_URL}\" target=\"_blank\" style=\"color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 28px; border: 19px solid #2DD05E; display: inline-block; border-radius: 8px;\">Acessar sua Área de Membros</a>\n                                        </td>\n                                    </tr>\n                                </table>\n                                <!-- END_IF_PRODUCT_TYPE_MEMBER_AREA -->\n\n                                <!-- Bloco para Link -->\n                                <!-- IF_PRODUCT_TYPE_LINK -->\n                                <p style=\"margin: 0 0 15px 0; font-size: 15px; color: #475569;\"><strong>Link de Acesso:</strong></p>\n                                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" style=\"border-collapse: collapse; margin-bottom: 10px;\">\n                                    <tr>\n                                        <td align=\"center\" style=\"background-color: #2DD05E; border-radius: 8px;\">\n                                            <!-- ### CORREÇÃO AQUI ### -->\n                                            <!-- Eu mudei o \'border: 1px\' para \'border: 19px\' para bater com o botão da área de membros. -->\n                                            <!-- Isso força o Outlook e outros clientes de e-mail a tornar toda a área do botão clicável. -->\n                                            <a href=\"{PRODUCT_LINK}\" target=\"_blank\" style=\"color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 28px; border: 19px solid #2DD05E; display: inline-block; border-radius: 8px;\">Acessar {PRODUCT_NAME}</a>\n                                        </td>\n                                    </tr>\n                                </table>\n                                <p style=\"word-break: break-all; font-size: 12px; color: #64748b;\">Se o botão não funcionar, copie e cole o link: <a href=\"{PRODUCT_LINK}\" style=\"color: #2DD05E;\">{PRODUCT_LINK}</a></p>\n                                <!-- END_IF_PRODUCT_TYPE_LINK -->\n\n                                <!-- Bloco para PDF -->\n                                <!-- IF_PRODUCT_TYPE_PDF -->\n                                <p style=\"margin: 0 0 10px 0; font-size: 15px; color: #475569;\">Seu PDF está anexado a este e-mail. Faça o download para começar a aproveitar!</p>\n                                <!-- END_IF_PRODUCT_TYPE_PDF -->\n                            </div>\n                            <!-- Fim do Loop de Produtos -->\n                            <!-- LOOP_PRODUCTS_END -->\n\n                            <p style=\"margin: 30px 0 0 0; font-size: 16px; line-height: 1.6; color: #475569;\">\n                                Caso tenha alguma dúvida ou precise de suporte, entre em contato conosco.\n                            </p>\n                            <p style=\"margin: 15px 0 0 0; font-size: 16px; line-height: 1.6; color: #475569;\">\n                                Obrigado e aproveite seus novos produtos!\n                            </p>\n                        </td>\n                    </tr>\n                    <!-- Rodapé -->\n                    <tr>\n                        <td align=\"center\" style=\"padding: 25px 30px; background-color: #f8fafc; border-top: 1px solid #e2e8f0;\">\n                            <p style=\"margin: 0; font-size: 13px; color: #64748b;\">\n                                Este é um e-mail automático, por favor, não responda.\n                            </p>\n                            <p style=\"margin: 10px 0 0 0; font-size: 13px; color: #94a3b8;\">\n                                GatewayPro &copy; 2025. Todos os direitos reservados.\n                            </p>\n                        </td>\n                    </tr>\n                </table>\n            </td>\n        </tr>\n    </table>\n</body>\n</html>'),
('email_template_delivery_subject', 'Acesso ao seu Produto GatewayPro!'),
('member_area_login_url', ''),
('mercado_pago_enable_credit_card', '1'),
('mercado_pago_enable_pix', '1'),
('mercado_pago_max_installments', '24'),
('smtp_encryption', 'ssl'),
('smtp_from_email', ''),
('smtp_from_name', 'GatewayPro'),
('smtp_host', 'smtp.gmail.com'),
('smtp_password', 'senha_app'),
('smtp_port', '465'),
('smtp_username', '');

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes_sistema`
--

CREATE TABLE `configuracoes_sistema` (
  `id` int(11) NOT NULL,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `tipo` varchar(50) DEFAULT 'text',
  `descricao` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `configuracoes_sistema`
--

INSERT INTO `configuracoes_sistema` (`id`, `chave`, `valor`, `tipo`, `descricao`, `created_at`, `updated_at`) VALUES
(1, 'cor_primaria', '#32e768', 'color', 'Cor primária do sistema', '2025-12-27 14:04:37', '2026-01-31 03:36:00'),
(2, 'logo_url', 'uploads/config/logo_1769830106.png', 'image', 'URL da logo do sistema', '2025-12-27 14:04:37', '2026-01-31 03:28:26'),
(3, 'login_image_url', 'uploads/config/login_bg_1766856615.jpg', 'image', 'URL da imagem de fundo da tela de login', '2025-12-27 14:04:37', '2025-12-27 17:30:15'),
(13, 'nome_plataforma', 'SinergIAClub', 'text', NULL, '2025-12-27 16:39:37', '2026-01-24 19:17:41'),
(14, 'logo_checkout_url', 'uploads/config/logo_checkout_1769831016.png', 'text', NULL, '2025-12-27 16:51:06', '2026-01-31 03:43:36'),
(15, 'favicon_url', 'uploads/config/favicon_1769830143.png', 'text', NULL, '2025-12-27 22:12:24', '2026-01-31 03:29:03'),
(16, 'master_panel_url', '', 'text', 'URL do painel master para validação de licenças', '2025-12-28 14:00:00', '2025-12-28 14:00:00'),
(17, 'master_panel_api_token', '', 'text', 'Token de autenticação da API do painel master', '2025-12-28 14:00:00', '2025-12-28 14:00:00'),
(18, 'license_key', 'GATEWAYPRO-VITALICIO-3D40424C-0D93717B', 'text', 'Chave de licença ativada', '2025-12-28 14:00:00', '2026-01-24 18:59:36'),
(19, 'license_status', 'active', 'text', 'Status da licença: active, expired, invalid', '2025-12-28 14:00:00', '2026-01-24 18:59:36'),
(20, 'license_expiration', 'lifetime', 'text', 'Data de expiração da licença ou lifetime', '2025-12-28 14:00:00', '2026-01-24 18:59:36'),
(21, 'license_activated_at', '2026-01-24 16:23:44', 'text', 'Data/hora da ativação da licença', '2025-12-28 14:00:00', '2026-01-24 19:23:44'),
(22, 'license_last_check', '2026-01-31 11:16:42', 'text', 'Última verificação da licença', '2025-12-28 14:00:00', '2026-01-31 14:16:42'),
(23, 'license_type', 'Vitalício', 'text', 'Tipo da licença: VITALICIO, ANUAL, SEMESTRAL, MENSAL', '2025-12-28 14:00:00', '2026-01-24 18:59:36'),
(24, 'license_days', '', 'text', 'Dias de validade da licença', '2025-12-28 14:00:00', '2025-12-28 14:00:00'),
(25, 'system_id', 'gp_697516981a5163.91734885', 'text', 'ID único desta instalação', '2025-12-28 14:00:00', '2026-01-24 18:59:36'),
(26, 'security_seal_url', 'uploads/config/security_seal_1769868821.png', 'text', NULL, '2026-01-31 03:54:14', '2026-01-31 14:13:41');

-- --------------------------------------------------------

--
-- Estrutura para tabela `cursos`
--

CREATE TABLE `cursos` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `imagem_url` varchar(255) DEFAULT NULL,
  `banner_url` varchar(255) DEFAULT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `cursos`
--

INSERT INTO `cursos` (`id`, `produto_id`, `titulo`, `descricao`, `imagem_url`, `banner_url`, `data_criacao`) VALUES
(16, 42, 'Checkout Inteligente Getfy', 'Seja dono da sua própria plataforma de vendas.\r\n\r\nO checkout inteligente que mais converte. Receba direto na sua conta, sem intermediários.\r\n\r\nMaximize Seus Lucros com Suas Próprias Taxas\r\nDinheiro cai direto na sua conta. Sem intermediários, sem surpresas.', 'uploads/69752b3b98e54.png', 'uploads/banner_curso_697d74899c8034.97981642.png', '2026-01-24 20:58:52'),
(17, 41, 'Ver Demosntração Getfy', '', 'uploads/697d1ce56e6b6.jpg', NULL, '2026-01-31 03:38:07');

-- --------------------------------------------------------

--
-- Estrutura para tabela `evolution_messages`
--

CREATE TABLE `evolution_messages` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor dono da mensagem',
  `produto_id` int(11) DEFAULT NULL COMMENT 'ID do produto específico (NULL para todos)',
  `name` varchar(255) NOT NULL COMMENT 'Nome identificador da mensagem',
  `event_type` enum('approved','pending','rejected','refunded','charged_back') NOT NULL COMMENT 'Evento que dispara a mensagem',
  `message_template` text NOT NULL COMMENT 'Template da mensagem com variáveis',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=inativo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gatewaypro_tracking_events`
--

CREATE TABLE `gatewaypro_tracking_events` (
  `id` int(11) NOT NULL,
  `tracking_product_id` int(11) NOT NULL COMMENT 'ID do produto rastreado em gatewaypro_tracking_products',
  `session_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'ID único da sessão do usuário',
  `event_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Tipo do evento (page_view, initiate_checkout, purchase)',
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Dados adicionais do evento (ex: url, referrer)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `gatewaypro_tracking_products`
--

CREATE TABLE `gatewaypro_tracking_products` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor dono do produto',
  `produto_id` int(11) NOT NULL COMMENT 'ID do produto real sendo rastreado',
  `tracking_id` varchar(64) NOT NULL COMMENT 'ID único para o script de rastreamento',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL COMMENT 'Endereço IP do cliente',
  `email` varchar(255) DEFAULT NULL COMMENT 'Email/usuário tentado (opcional)',
  `attempts` int(11) NOT NULL DEFAULT 1 COMMENT 'Número de tentativas falhas',
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data/hora da última tentativa',
  `blocked_until` timestamp NULL DEFAULT NULL COMMENT 'Data/hora até quando está bloqueado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Rastreamento de tentativas de login para proteção contra força bruta';

-- --------------------------------------------------------

--
-- Estrutura para tabela `modulos`
--

CREATE TABLE `modulos` (
  `id` int(11) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `imagem_capa_url` varchar(255) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `release_days` int(11) NOT NULL DEFAULT 0 COMMENT 'Número de dias após a compra para o módulo ser liberado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `modulos`
--

INSERT INTO `modulos` (`id`, `curso_id`, `titulo`, `imagem_capa_url`, `ordem`, `release_days`) VALUES
(18, 16, 'Módulo 01: Instalação', 'uploads/imagem_capa_modulo_697d376fd20195.01633729.png', 0, 0),
(19, 16, 'Módulo 02: Explorando a Plataforma', 'uploads/imagem_capa_modulo_697d34d323a035.19058999.png', 0, 0),
(20, 16, 'Módulo 03 - Integrando Gateway\'s', 'uploads/imagem_capa_modulo_697d34e1a4bb80.06332789.png', 0, 0),
(21, 16, 'Módulo 04 - Atualizações', 'uploads/imagem_capa_modulo_697d34f309bce1.89249757.png', 0, 0),
(22, 16, 'Módulo 05 - Roadmap', 'uploads/imagem_capa_modulo_697d350712f7f6.29336013.png', 0, 0),
(23, 16, 'Módulo 06 - Comunidade & Networking', 'uploads/imagem_capa_modulo_697d352f2a4768.04347508.png', 0, 0),
(24, 16, 'Módulo 07 - Instalação Paga', 'uploads/imagem_capa_modulo_697d354e0edd15.69826320.png', 0, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `notificacoes`
--

CREATE TABLE `notificacoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor que deve receber a notificação',
  `tipo` varchar(50) NOT NULL COMMENT 'Tipo de evento (ex: Compra Aprovada, Pix Gerado, Boleto Pago)',
  `mensagem` text NOT NULL COMMENT 'Mensagem completa da notificação',
  `valor` decimal(10,2) DEFAULT NULL COMMENT 'Valor associado à notificação (ex: valor da venda)',
  `data_notificacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `lida` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 para não lida, 1 para lida',
  `link_acao` varchar(255) DEFAULT NULL COMMENT 'Link opcional para detalhes da venda',
  `displayed_live` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 para não exibida ao vivo, 1 para já exibida ao vivo',
  `venda_id_fk` int(11) DEFAULT NULL COMMENT 'Chave estrangeira para a tabela de vendas',
  `metodo_pagamento` varchar(50) DEFAULT NULL COMMENT 'Método de pagamento da venda associada, para notificação live'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `order_bumps`
--

CREATE TABLE `order_bumps` (
  `id` int(11) NOT NULL,
  `main_product_id` int(11) NOT NULL COMMENT 'ID do produto principal (o do checkout)',
  `offer_product_id` int(11) NOT NULL COMMENT 'ID do produto que está sendo ofertado',
  `headline` varchar(255) DEFAULT 'Sim, eu quero aproveitar essa oferta!',
  `description` text DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição no checkout',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `plugins`
--

CREATE TABLE `plugins` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `pasta` varchar(100) NOT NULL,
  `versao` varchar(20) DEFAULT '1.0.0',
  `ativo` tinyint(1) DEFAULT 0,
  `instalado_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `plugins`
--

INSERT INTO `plugins` (`id`, `nome`, `pasta`, `versao`, `ativo`, `instalado_em`, `atualizado_em`) VALUES
(4, 'Modo SaaS', 'saas', '1.0.0', 0, '2025-12-28 10:05:12', '2025-12-28 10:05:12');

-- --------------------------------------------------------

--
-- Estrutura para tabela `product_exclusive_offers`
--

CREATE TABLE `product_exclusive_offers` (
  `id` int(11) NOT NULL,
  `source_product_id` int(11) NOT NULL COMMENT 'ID do produto que o cliente já possui e que gera a oferta',
  `offer_product_id` int(11) NOT NULL COMMENT 'ID do produto (tipo area_membros) ofertado',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Status da oferta: 1=ativo, 0=inativo',
  `custom_link` varchar(500) DEFAULT NULL COMMENT 'Link personalizado para a oferta. Se NULL, usa o checkout padrão do produto.',
  `custom_button_text` varchar(100) DEFAULT NULL COMMENT 'Texto personalizado do botão. Se NULL, usa "Comprar por R$ X,XX".',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `product_exclusive_offers`
--

INSERT INTO `product_exclusive_offers` (`id`, `source_product_id`, `offer_product_id`, `is_active`, `custom_link`, `custom_button_text`, `created_at`) VALUES
(5, 41, 42, 1, NULL, NULL, '2026-01-24 22:47:42'),
(6, 42, 41, 1, NULL, NULL, '2026-01-31 03:37:47');

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL,
  `is_free` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se o produto é gratuito (1=grátis, 0=pago)',
  `is_showcase` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se o produto é vitrine para registro gratuito (1=vitrine, 0=normal)',
  `foto` varchar(255) DEFAULT NULL,
  `checkout_hash` varchar(255) NOT NULL,
  `checkout_config` text DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `preco_anterior` decimal(10,2) DEFAULT NULL,
  `tipo_entrega` varchar(50) NOT NULL DEFAULT 'link',
  `conteudo_entrega` varchar(255) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT 'mercadopago',
  `gera_licenca` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Se este produto permite gerar licenças (apenas no painel master)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `produtos`
--

INSERT INTO `produtos` (`id`, `nome`, `descricao`, `preco`, `is_free`, `is_showcase`, `foto`, `checkout_hash`, `checkout_config`, `usuario_id`, `data_criacao`, `preco_anterior`, `tipo_entrega`, `conteudo_entrega`, `gateway`, `gera_licenca`) VALUES
(41, 'Ver Demosntração Getfy', '', 0.00, 0, 1, '697d1ce56e6b6.jpg', '75fffd8b17dc47c56a6ccbe1990befd8', '{\n    \"banners\": [],\n    \"sideBanners\": [],\n    \"elementOrder\": [\n        \"header\",\n        \"banner\",\n        \"youtube_video\",\n        \"summary\",\n        \"customer_info\",\n        \"order_bump\",\n        \"final_summary\",\n        \"payment\",\n        \"guarantee\",\n        \"security_info\"\n    ]\n}', 33, '2026-01-24 19:32:28', NULL, 'area_membros', NULL, 'mercadopago', 0),
(42, 'Checkout Inteligente Getfy', 'Seja dono da sua própria plataforma de vendas.\r\n\r\nO checkout inteligente que mais converte. Receba direto na sua conta, sem intermediários.\r\n\r\nMaximize Seus Lucros com Suas Próprias Taxas\r\nDinheiro cai direto na sua conta. Sem intermediários, sem surpresas.', 0.00, 1, 0, '697d1cb42f12e.jpg', '840558feeee1885cee861a36abf5038a', '{\n    \"banners\": [\n        \"uploads/banner_42_1769830739_0.png\"\n    ],\n    \"sideBanners\": [\n        \"uploads/sidebanner_42_1769830739_0.png\"\n    ],\n    \"backgroundColor\": \"#f3f4f6\",\n    \"accentColor\": \"#24A148\",\n    \"redirectUrl\": \"\",\n    \"youtubeUrl\": \"\",\n    \"tracking\": {\n        \"facebookPixelId\": \"\",\n        \"facebookApiToken\": \"\",\n        \"googleAnalyticsId\": \"\",\n        \"googleAdsId\": \"\",\n        \"events\": {\n            \"facebook\": {\n                \"purchase\": false,\n                \"pending\": false,\n                \"refund\": false,\n                \"chargeback\": false,\n                \"rejected\": false,\n                \"initiate_checkout\": false\n            },\n            \"google\": {\n                \"purchase\": false,\n                \"pending\": false,\n                \"refund\": false,\n                \"chargeback\": false,\n                \"rejected\": false,\n                \"initiate_checkout\": false\n            }\n        }\n    },\n    \"summary\": {\n        \"product_name\": \"Checkout Inteligente Getfy\",\n        \"discount_text\": \"\"\n    },\n    \"header\": {\n        \"enabled\": true,\n        \"title\": \"Finalize sua Compra\",\n        \"subtitle\": \"Ambiente 100% seguro\"\n    },\n    \"timer\": {\n        \"enabled\": true,\n        \"minutes\": 15,\n        \"text\": \"Esta oferta expira em:\",\n        \"bgcolor\": \"#000000\",\n        \"textcolor\": \"#FFFFFF\",\n        \"sticky\": true\n    },\n    \"salesNotification\": {\n        \"enabled\": true,\n        \"names\": \"\",\n        \"product\": \"Checkout Inteligente Getfy\",\n        \"tempo_exibicao\": 5,\n        \"intervalo_notificacao\": 10\n    },\n    \"paymentMethods\": {\n        \"credit_card\": false,\n        \"pix\": false,\n        \"ticket\": false\n    },\n    \"backRedirect\": {\n        \"enabled\": false,\n        \"url\": \"\"\n    },\n    \"elementOrder\": [\n        \"header\",\n        \"banner\",\n        \"youtube_video\",\n        \"summary\",\n        \"customer_info\",\n        \"order_bump\",\n        \"final_summary\",\n        \"payment\",\n        \"guarantee\",\n        \"security_info\"\n    ],\n    \"customer_fields\": {\n        \"enable_cpf\": false,\n        \"enable_phone\": true\n    },\n    \"legalLinks\": {\n        \"privacyUrl\": \"https://gatewaypro1.vitrineacademy.com.br/legal/privacidade.html\",\n        \"termsUrl\": \"https://gatewaypro1.vitrineacademy.com.br/legal/termos.html\"\n    }\n}', 33, '2026-01-24 20:27:39', NULL, 'area_membros', NULL, 'mercadopago', 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_ofertas`
--

CREATE TABLE `produto_ofertas` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL COMMENT 'ID do produto principal',
  `nome` varchar(255) NOT NULL COMMENT 'Nome da oferta (ex: Black Friday, Lançamento)',
  `preco` decimal(10,2) NOT NULL COMMENT 'Preço específico desta oferta',
  `tipo_acesso` enum('mensal','semestral','anual','vitalicio') NOT NULL DEFAULT 'vitalicio' COMMENT 'Tipo de acesso: mensal (30 dias), semestral (180 dias), anual (365 dias), vitalicio (sem expiração)',
  `hash` varchar(64) NOT NULL COMMENT 'Hash único para o link da oferta',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=inativo',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_atualizacao` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `saas_assinaturas`
--

CREATE TABLE `saas_assinaturas` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `plano_id` int(11) NOT NULL,
  `status` enum('ativo','expirado','cancelado','pendente') DEFAULT 'pendente',
  `data_inicio` datetime DEFAULT current_timestamp(),
  `data_vencimento` datetime NOT NULL,
  `transacao_id` varchar(255) DEFAULT NULL,
  `gateway` varchar(50) DEFAULT NULL,
  `renovacao_automatica` tinyint(1) DEFAULT 1,
  `notificado_vencimento` tinyint(1) DEFAULT 0,
  `notificado_expirado` tinyint(1) DEFAULT 0,
  `criado_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `saas_assinaturas`
--

INSERT INTO `saas_assinaturas` (`id`, `usuario_id`, `plano_id`, `status`, `data_inicio`, `data_vencimento`, `transacao_id`, `gateway`, `renovacao_automatica`, `notificado_vencimento`, `notificado_expirado`, `criado_em`, `atualizado_em`) VALUES
(2, 1, 1, 'cancelado', '2025-12-27 20:37:37', '2026-01-26 20:37:37', NULL, NULL, 1, 0, 0, '2025-12-27 20:37:37', '2025-12-28 10:04:53');

-- --------------------------------------------------------

--
-- Estrutura para tabela `saas_config_admin`
--

CREATE TABLE `saas_config_admin` (
  `id` int(11) NOT NULL,
  `mp_access_token` text DEFAULT NULL,
  `mp_public_key` varchar(255) DEFAULT NULL,
  `pushinpay_token` text DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_methods` text DEFAULT NULL COMMENT 'JSON com métodos de pagamento habilitados'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `saas_config_admin`
--

INSERT INTO `saas_config_admin` (`id`, `mp_access_token`, `mp_public_key`, `pushinpay_token`, `ativo`, `atualizado_em`, `payment_methods`) VALUES
(1, 'APP_USR-000', 'APP_USR-000', '58271|000', 1, '2025-12-27 22:00:11', '{\"pix\":{\"gateway\":\"pushinpay\",\"enabled\":true},\"credit_card\":{\"gateway\":\"mercadopago\",\"enabled\":true},\"ticket\":{\"gateway\":\"mercadopago\",\"enabled\":true}}'),
(2, NULL, NULL, NULL, 1, '2025-12-27 20:14:54', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `saas_limites_uso`
--

CREATE TABLE `saas_limites_uso` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `mes_ano` varchar(7) NOT NULL COMMENT 'Formato: YYYY-MM',
  `produtos_criados` int(11) DEFAULT 0,
  `pedidos_realizados` int(11) DEFAULT 0,
  `resetado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `saas_planos`
--

CREATE TABLE `saas_planos` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) DEFAULT 0.00,
  `periodo` enum('mensal','anual') DEFAULT 'mensal',
  `max_produtos` int(11) DEFAULT NULL COMMENT 'NULL = ilimitado',
  `max_pedidos_mes` int(11) DEFAULT NULL COMMENT 'NULL = ilimitado',
  `tracking_enabled` tinyint(1) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` datetime DEFAULT current_timestamp(),
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `saas_planos`
--

INSERT INTO `saas_planos` (`id`, `nome`, `descricao`, `preco`, `periodo`, `max_produtos`, `max_pedidos_mes`, `tracking_enabled`, `ativo`, `criado_em`, `atualizado_em`) VALUES
(1, 'Plano Free', 'Plano gratuito para começar', 0.00, 'mensal', 3, 10, 0, 1, '2025-12-27 19:52:55', '2025-12-27 19:52:55'),
(2, 'Premium', 'Descr', 35.00, 'mensal', NULL, NULL, 1, 1, '2025-12-27 20:02:00', '2025-12-27 20:02:00');

-- --------------------------------------------------------

--
-- Estrutura para tabela `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL COMMENT 'Tipo do evento (failed_login_attempt, blocked_login_attempt, unauthorized_access, etc)',
  `user_id` int(11) DEFAULT NULL COMMENT 'ID do usuário (se aplicável)',
  `ip_address` varchar(45) NOT NULL COMMENT 'Endereço IP do cliente',
  `details` text DEFAULT NULL COMMENT 'Detalhes do evento em JSON',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data/hora do evento'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Logs de eventos de segurança para auditoria';

--
-- Despejando dados para a tabela `security_logs`
--

INSERT INTO `security_logs` (`id`, `event_type`, `user_id`, `ip_address`, `details`, `created_at`) VALUES
(1, 'failed_login_attempt', NULL, '45.183.83.214', '{\"ip\":\"45.183.83.214\",\"email\":\"admin@gmail.com\",\"attempts\":1,\"blocked_until\":null}', '2026-01-24 18:59:52'),
(2, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:1da3:3d31:15d1:b2b1', '{\"ip\":\"2804:90ec:5022:3780:1da3:3d31:15d1:b2b1\",\"email\":\"sinergiahub@gmail.com\",\"attempts\":1,\"blocked_until\":null}', '2026-01-24 22:06:26'),
(3, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:1da3:3d31:15d1:b2b1', '{\"ip\":\"2804:90ec:5022:3780:1da3:3d31:15d1:b2b1\",\"email\":\"admin@admin.com\",\"attempts\":1,\"blocked_until\":null}', '2026-01-24 22:06:47'),
(4, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:1da3:3d31:15d1:b2b1', '{\"ip\":\"2804:90ec:5022:3780:1da3:3d31:15d1:b2b1\",\"email\":\"admin@admin.com\",\"attempts\":2,\"blocked_until\":null}', '2026-01-24 22:06:59'),
(5, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:1da3:3d31:15d1:b2b1', '{\"ip\":\"2804:90ec:5022:3780:1da3:3d31:15d1:b2b1\",\"email\":\"sinergiahub@gmail.com\",\"attempts\":2,\"blocked_until\":null}', '2026-01-24 22:07:05'),
(6, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:1da3:3d31:15d1:b2b1', '{\"ip\":\"2804:90ec:5022:3780:1da3:3d31:15d1:b2b1\",\"email\":\"admin@admin.com\",\"attempts\":3,\"blocked_until\":null}', '2026-01-24 22:07:47'),
(7, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:1da3:3d31:15d1:b2b1', '{\"ip\":\"2804:90ec:5022:3780:1da3:3d31:15d1:b2b1\",\"email\":\"admin@admin.com\",\"attempts\":4,\"blocked_until\":null}', '2026-01-24 22:07:56'),
(8, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:1da3:3d31:15d1:b2b1', '{\"ip\":\"2804:90ec:5022:3780:1da3:3d31:15d1:b2b1\",\"email\":\"admin@gmail.com\",\"attempts\":1,\"blocked_until\":null}', '2026-01-24 22:08:18'),
(9, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:1da3:3d31:15d1:b2b1', '{\"ip\":\"2804:90ec:5022:3780:1da3:3d31:15d1:b2b1\",\"email\":\"admin@admin.com\",\"attempts\":5,\"blocked_until\":\"2026-01-24 19:27:28\"}', '2026-01-24 22:12:28'),
(10, 'blocked_login_attempt', NULL, '2804:90ec:5022:3780:1da3:3d31:15d1:b2b1', '{\"ip\":\"2804:90ec:5022:3780:1da3:3d31:15d1:b2b1\",\"email\":\"admin@gmail.com\",\"reason\":\"IP bloqueado temporariamente\"}', '2026-01-24 22:14:00'),
(11, 'blocked_login_attempt', NULL, '2804:90ec:5022:3780:1da3:3d31:15d1:b2b1', '{\"ip\":\"2804:90ec:5022:3780:1da3:3d31:15d1:b2b1\",\"email\":\"vandragoni@gmail.com\",\"reason\":\"IP bloqueado temporariamente\"}', '2026-01-24 22:14:14'),
(12, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:1da3:3d31:15d1:b2b1', '{\"ip\":\"2804:90ec:5022:3780:1da3:3d31:15d1:b2b1\",\"email\":\"admin@gmail.com\",\"attempts\":2,\"blocked_until\":null}', '2026-01-24 22:46:52'),
(13, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:59dd:30f5:393:7b27', '{\"ip\":\"2804:90ec:5022:3780:59dd:30f5:393:7b27\",\"email\":\"upperaws@gmail.com\",\"attempts\":1,\"blocked_until\":null}', '2026-01-30 21:05:13'),
(14, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:59dd:30f5:393:7b27', '{\"ip\":\"2804:90ec:5022:3780:59dd:30f5:393:7b27\",\"email\":\"upperaws@gmail.com\",\"attempts\":2,\"blocked_until\":null}', '2026-01-30 21:05:21'),
(15, 'failed_login_attempt', NULL, '2804:90ec:5022:3780:a876:4785:b5c8:bae0', '{\"ip\":\"2804:90ec:5022:3780:a876:4785:b5c8:bae0\",\"email\":\"upperaws@gmail.com\",\"attempts\":1,\"blocked_until\":null}', '2026-01-31 03:31:32');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(255) NOT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` varchar(20) NOT NULL DEFAULT 'infoprodutor' COMMENT 'Define o tipo de usuário (admin, infoprodutor, usuario[cliente])',
  `mp_public_key` varchar(255) DEFAULT NULL,
  `mp_access_token` varchar(255) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `ultima_visualizacao_notificacoes` timestamp NULL DEFAULT NULL COMMENT 'Timestamp da última vez que o usuário visualizou o painel de notificações',
  `pushinpay_token` varchar(255) DEFAULT NULL,
  `evolution_name` varchar(255) DEFAULT NULL COMMENT 'Nome da integração Evolution API',
  `evolution_server_url` varchar(500) DEFAULT NULL COMMENT 'URL do servidor Evolution API',
  `evolution_api_key` varchar(255) DEFAULT NULL COMMENT 'API Key global da Evolution API',
  `evolution_instance` varchar(255) DEFAULT NULL COMMENT 'Nome da instância na Evolution API',
  `efi_client_id` varchar(255) DEFAULT NULL COMMENT 'Client ID da aplicação Efí',
  `efi_client_secret` varchar(255) DEFAULT NULL COMMENT 'Client Secret da aplicação Efí',
  `efi_certificate_path` varchar(500) DEFAULT NULL COMMENT 'Caminho do certificado P12 da Efí',
  `efi_pix_key` varchar(255) DEFAULT NULL COMMENT 'Chave Pix cadastrada na Efí',
  `efi_payee_code` varchar(255) DEFAULT NULL COMMENT 'Código do recebedor Efí para cartão de crédito',
  `beehive_secret_key` varchar(255) DEFAULT NULL COMMENT 'Secret Key da Beehive',
  `beehive_public_key` varchar(255) DEFAULT NULL COMMENT 'Public Key da Beehive',
  `hypercash_secret_key` varchar(255) DEFAULT NULL COMMENT 'Secret Key da Hypercash',
  `hypercash_public_key` varchar(255) DEFAULT NULL COMMENT 'Public Key da Hypercash'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `nome`, `telefone`, `senha`, `tipo`, `mp_public_key`, `mp_access_token`, `foto_perfil`, `ultima_visualizacao_notificacoes`, `pushinpay_token`, `evolution_name`, `evolution_server_url`, `evolution_api_key`, `evolution_instance`, `efi_client_id`, `efi_client_secret`, `efi_certificate_path`, `efi_pix_key`, `efi_payee_code`, `beehive_secret_key`, `beehive_public_key`, `hypercash_secret_key`, `hypercash_public_key`) VALUES
(1, 'admin@gmail.com', 'Super Admin', '', '$2y$10$lTXZqS7J/dsIHwTQWqAjduKpe/qZ6KGMMyvswxDamZcswA9Fp3GU.', 'admin', 'APP_USR-8280168838657077-122720-e71ccc6f4cd08110af93ddbd9ba97242-599681281', 'APP_USR-66805517-8069-4f60-8249-fca6d6714d6e', NULL, '2025-09-15 16:35:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(33, 'vandragoni@gmail.com', 'Ivan Luiz de Souza', '1198071943', '$2y$10$dyhKjPTfdWEgHk5KWSyoDuhff6Nn2rHW6kmOIraQUwTMZcrYM7.dS', 'infoprodutor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(34, 'hojeitocerto@gmail.com', 'Ivan Luiz de Souza', NULL, '$2y$10$QgbkJoDspi.gRes27169iO7YXmFRT6DkDe1qzYiKl/yz9O6M4ajD.', 'usuario', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(35, 'gildenefsouza@gmail.com', 'Gildene Ferreira de Souza', NULL, '$2y$10$f/7Bo7hmNYO85b5fAbMybOsBQ.tnoJh42x7kF0NKXUKMz4j/5KXtG', 'usuario', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `utmfy_integrations`
--

CREATE TABLE `utmfy_integrations` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor dono da integração',
  `name` varchar(255) NOT NULL COMMENT 'Nome amigável da integração (ex: Campanha de Lançamento X)',
  `api_token` varchar(255) NOT NULL COMMENT 'API Token fornecido pela UTMfy',
  `product_id` int(11) DEFAULT NULL COMMENT 'ID do produto específico que dispara a notificação (NULL para todos os produtos do infoprodutor)',
  `event_approved` tinyint(1) NOT NULL DEFAULT 0,
  `event_pending` tinyint(1) NOT NULL DEFAULT 0,
  `event_rejected` tinyint(1) NOT NULL DEFAULT 0,
  `event_refunded` tinyint(1) NOT NULL DEFAULT 0,
  `event_charged_back` tinyint(1) NOT NULL DEFAULT 0,
  `event_initiate_checkout` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Disparar evento ao iniciar checkout',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `vendas`
--

CREATE TABLE `vendas` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `oferta_id` int(11) DEFAULT NULL COMMENT 'ID da oferta usada (NULL se preço padrão)',
  `valor` decimal(10,2) NOT NULL,
  `status_pagamento` varchar(50) NOT NULL,
  `data_venda` timestamp NOT NULL DEFAULT current_timestamp(),
  `comprador_email` varchar(255) DEFAULT NULL,
  `comprador_nome` varchar(255) DEFAULT NULL,
  `comprador_cpf` varchar(20) DEFAULT NULL,
  `comprador_telefone` varchar(20) DEFAULT NULL,
  `transacao_id` varchar(255) DEFAULT NULL,
  `metodo_pagamento` varchar(50) DEFAULT NULL,
  `checkout_session_uuid` varchar(255) DEFAULT NULL COMMENT 'UUID para agrupar vendas de um mesmo checkout (principal + order bumps)',
  `email_entrega_enviado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = Não enviado, 1 = Enviado',
  `utm_source` varchar(255) DEFAULT NULL,
  `utm_campaign` varchar(255) DEFAULT NULL,
  `utm_medium` varchar(255) DEFAULT NULL,
  `utm_content` varchar(255) DEFAULT NULL,
  `utm_term` varchar(255) DEFAULT NULL,
  `src` varchar(255) DEFAULT NULL,
  `sck` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `vendas`
--

INSERT INTO `vendas` (`id`, `produto_id`, `oferta_id`, `valor`, `status_pagamento`, `data_venda`, `comprador_email`, `comprador_nome`, `comprador_cpf`, `comprador_telefone`, `transacao_id`, `metodo_pagamento`, `checkout_session_uuid`, `email_entrega_enviado`, `utm_source`, `utm_campaign`, `utm_medium`, `utm_content`, `utm_term`, `src`, `sck`) VALUES
(240, 41, NULL, 0.00, 'approved', '2026-01-24 22:15:51', 'hojeitocerto@gmail.com', 'Ivan Luiz de Souza', NULL, NULL, 'FREE_REG_697544975e4d8_cc16eade', 'Registro Grátis', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(241, 42, NULL, 0.00, 'approved', '2026-01-24 23:18:59', 'hojeitocerto@gmail.com', 'Ivan Luiz de Souza', '16943869884', '11981071943', 'FREE_69755363ddd89_7712bd41', 'Grátis', 'free_69755363ddd8f4eaae007b0bd1313', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(242, 42, NULL, 0.00, 'approved', '2026-01-24 23:24:43', 'hojeitocerto@gmail.com', 'Cliente Final', '16943869884', '11987424212', 'FREE_697554bbc8b28_0642227d', 'Grátis', 'free_697554bbc8b2f02ccb693cc49b0cc', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(243, 42, NULL, 0.00, 'approved', '2026-01-31 03:55:43', 'gildenefsouza@gmail.com', 'Gildene Ferreira de Souza', '00000000000', '11987424212', 'FREE_697d7d3f1eb4b_eee94aa3', 'Grátis', 'free_697d7d3f1eb52aaadcb2df20fcbff', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `webhooks`
--

CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'ID do infoprodutor dono do webhook',
  `produto_id` int(11) DEFAULT NULL COMMENT 'ID do produto específico que dispara o webhook (NULL para todos os produtos do infoprodutor)',
  `url` varchar(2048) NOT NULL COMMENT 'URL para onde o webhook será enviado',
  `event_approved` tinyint(1) NOT NULL DEFAULT 0,
  `event_pending` tinyint(1) NOT NULL DEFAULT 0,
  `event_rejected` tinyint(1) NOT NULL DEFAULT 0,
  `event_refunded` tinyint(1) NOT NULL DEFAULT 0,
  `event_charged_back` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `alunos_acessos`
--
ALTER TABLE `alunos_acessos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `aluno_produto_unico` (`aluno_email`,`produto_id`),
  ADD KEY `idx_produto_id` (`produto_id`),
  ADD KEY `idx_aluno_email` (`aluno_email`),
  ADD KEY `idx_data_expiracao` (`data_expiracao`),
  ADD KEY `idx_criado_manualmente` (`criado_manualmente`);

--
-- Índices de tabela `aluno_progresso`
--
ALTER TABLE `aluno_progresso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `aluno_aula_unico` (`aluno_email`,`aula_id`),
  ADD KEY `idx_aula_id` (`aula_id`);

--
-- Índices de tabela `aulas`
--
ALTER TABLE `aulas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_modulo_id` (`modulo_id`);

--
-- Índices de tabela `aula_arquivos`
--
ALTER TABLE `aula_arquivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_aula_arquivos_aula` (`aula_id`);

--
-- Índices de tabela `cloned_sites`
--
ALTER TABLE `cloned_sites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cloned_sites_usuario` (`usuario_id`);

--
-- Índices de tabela `cloned_site_settings`
--
ALTER TABLE `cloned_site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_cloned_site_settings_unique` (`cloned_site_id`),
  ADD KEY `fk_cloned_site_settings_site` (`cloned_site_id`);

--
-- Índices de tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  ADD PRIMARY KEY (`chave`);

--
-- Índices de tabela `configuracoes_sistema`
--
ALTER TABLE `configuracoes_sistema`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave` (`chave`);

--
-- Índices de tabela `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_produto_id_cursos` (`produto_id`);

--
-- Índices de tabela `evolution_messages`
--
ALTER TABLE `evolution_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_id` (`usuario_id`),
  ADD KEY `idx_produto_id` (`produto_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Índices de tabela `gatewaypro_tracking_events`
--
ALTER TABLE `gatewaypro_tracking_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tracking_events_product` (`tracking_product_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `gatewaypro_tracking_products`
--
ALTER TABLE `gatewaypro_tracking_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_tracking_id` (`tracking_id`),
  ADD UNIQUE KEY `idx_unique_usuario_produto_rastreado` (`usuario_id`,`produto_id`),
  ADD KEY `fk_tracking_products_usuario` (`usuario_id`),
  ADD KEY `fk_tracking_products_produto` (`produto_id`);

--
-- Índices de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_blocked_until` (`blocked_until`),
  ADD KEY `idx_last_attempt` (`last_attempt`),
  ADD KEY `idx_ip_email` (`ip_address`,`email`);

--
-- Índices de tabela `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_curso_id` (`curso_id`);

--
-- Índices de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_id_notificacoes` (`usuario_id`),
  ADD KEY `idx_lida_data_notificacao` (`lida`,`data_notificacao`),
  ADD KEY `fk_notificacoes_venda` (`venda_id_fk`);

--
-- Índices de tabela `order_bumps`
--
ALTER TABLE `order_bumps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_main_product_id` (`main_product_id`),
  ADD KEY `fk_order_bumps_offer_product` (`offer_product_id`);

--
-- Índices de tabela `plugins`
--
ALTER TABLE `plugins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`),
  ADD UNIQUE KEY `pasta` (`pasta`),
  ADD KEY `idx_ativo` (`ativo`),
  ADD KEY `idx_pasta` (`pasta`);

--
-- Índices de tabela `product_exclusive_offers`
--
ALTER TABLE `product_exclusive_offers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_product_offer` (`source_product_id`,`offer_product_id`),
  ADD KEY `fk_offer_source_product` (`source_product_id`),
  ADD KEY `fk_offer_target_product` (`offer_product_id`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_id` (`usuario_id`);

--
-- Índices de tabela `produto_ofertas`
--
ALTER TABLE `produto_ofertas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_hash` (`hash`),
  ADD KEY `idx_produto_id` (`produto_id`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `saas_assinaturas`
--
ALTER TABLE `saas_assinaturas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plano_id` (`plano_id`),
  ADD KEY `idx_usuario_id` (`usuario_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_data_vencimento` (`data_vencimento`);

--
-- Índices de tabela `saas_config_admin`
--
ALTER TABLE `saas_config_admin`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `saas_limites_uso`
--
ALTER TABLE `saas_limites_uso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_usuario_mes` (`usuario_id`,`mes_ano`),
  ADD KEY `idx_usuario_mes` (`usuario_id`,`mes_ano`);

--
-- Índices de tabela `saas_planos`
--
ALTER TABLE `saas_planos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Índices de tabela `utmfy_integrations`
--
ALTER TABLE `utmfy_integrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_utmfy_integrations_usuario` (`usuario_id`),
  ADD KEY `fk_utmfy_integrations_produto` (`product_id`);

--
-- Índices de tabela `vendas`
--
ALTER TABLE `vendas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_produto_id_vendas` (`produto_id`),
  ADD KEY `idx_checkout_session_uuid` (`checkout_session_uuid`),
  ADD KEY `idx_vendas_oferta_id` (`oferta_id`);

--
-- Índices de tabela `webhooks`
--
ALTER TABLE `webhooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_webhooks_usuario` (`usuario_id`),
  ADD KEY `fk_webhooks_produto` (`produto_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alunos_acessos`
--
ALTER TABLE `alunos_acessos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de tabela `aluno_progresso`
--
ALTER TABLE `aluno_progresso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `aulas`
--
ALTER TABLE `aulas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de tabela `aula_arquivos`
--
ALTER TABLE `aula_arquivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `cloned_sites`
--
ALTER TABLE `cloned_sites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT de tabela `cloned_site_settings`
--
ALTER TABLE `cloned_site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT de tabela `configuracoes_sistema`
--
ALTER TABLE `configuracoes_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de tabela `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `evolution_messages`
--
ALTER TABLE `evolution_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `gatewaypro_tracking_events`
--
ALTER TABLE `gatewaypro_tracking_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `gatewaypro_tracking_products`
--
ALTER TABLE `gatewaypro_tracking_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de tabela `notificacoes`
--
ALTER TABLE `notificacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=245;

--
-- AUTO_INCREMENT de tabela `order_bumps`
--
ALTER TABLE `order_bumps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT de tabela `plugins`
--
ALTER TABLE `plugins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `product_exclusive_offers`
--
ALTER TABLE `product_exclusive_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de tabela `produto_ofertas`
--
ALTER TABLE `produto_ofertas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `saas_assinaturas`
--
ALTER TABLE `saas_assinaturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `saas_config_admin`
--
ALTER TABLE `saas_config_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `saas_limites_uso`
--
ALTER TABLE `saas_limites_uso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `saas_planos`
--
ALTER TABLE `saas_planos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de tabela `utmfy_integrations`
--
ALTER TABLE `utmfy_integrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `vendas`
--
ALTER TABLE `vendas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=244;

--
-- AUTO_INCREMENT de tabela `webhooks`
--
ALTER TABLE `webhooks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `alunos_acessos`
--
ALTER TABLE `alunos_acessos`
  ADD CONSTRAINT `fk_alunos_acessos_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `aluno_progresso`
--
ALTER TABLE `aluno_progresso`
  ADD CONSTRAINT `fk_aluno_progresso_aula` FOREIGN KEY (`aula_id`) REFERENCES `aulas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `aulas`
--
ALTER TABLE `aulas`
  ADD CONSTRAINT `fk_aulas_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `aula_arquivos`
--
ALTER TABLE `aula_arquivos`
  ADD CONSTRAINT `fk_aula_arquivos_aula` FOREIGN KEY (`aula_id`) REFERENCES `aulas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cloned_sites`
--
ALTER TABLE `cloned_sites`
  ADD CONSTRAINT `fk_cloned_sites_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cloned_site_settings`
--
ALTER TABLE `cloned_site_settings`
  ADD CONSTRAINT `fk_cloned_site_settings_site` FOREIGN KEY (`cloned_site_id`) REFERENCES `cloned_sites` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `cursos`
--
ALTER TABLE `cursos`
  ADD CONSTRAINT `fk_cursos_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `gatewaypro_tracking_events`
--
ALTER TABLE `gatewaypro_tracking_events`
  ADD CONSTRAINT `fk_tracking_events_product` FOREIGN KEY (`tracking_product_id`) REFERENCES `gatewaypro_tracking_products` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `gatewaypro_tracking_products`
--
ALTER TABLE `gatewaypro_tracking_products`
  ADD CONSTRAINT `fk_tracking_products_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tracking_products_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `modulos`
--
ALTER TABLE `modulos`
  ADD CONSTRAINT `fk_modulos_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `notificacoes`
--
ALTER TABLE `notificacoes`
  ADD CONSTRAINT `fk_notificacoes_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notificacoes_venda` FOREIGN KEY (`venda_id_fk`) REFERENCES `vendas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `order_bumps`
--
ALTER TABLE `order_bumps`
  ADD CONSTRAINT `fk_order_bumps_main_product` FOREIGN KEY (`main_product_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_bumps_offer_product` FOREIGN KEY (`offer_product_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `product_exclusive_offers`
--
ALTER TABLE `product_exclusive_offers`
  ADD CONSTRAINT `fk_offer_source_product` FOREIGN KEY (`source_product_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_offer_target_product` FOREIGN KEY (`offer_product_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `fk_produtos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `produto_ofertas`
--
ALTER TABLE `produto_ofertas`
  ADD CONSTRAINT `fk_produto_ofertas_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `saas_assinaturas`
--
ALTER TABLE `saas_assinaturas`
  ADD CONSTRAINT `saas_assinaturas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `saas_assinaturas_ibfk_2` FOREIGN KEY (`plano_id`) REFERENCES `saas_planos` (`id`);

--
-- Restrições para tabelas `saas_limites_uso`
--
ALTER TABLE `saas_limites_uso`
  ADD CONSTRAINT `saas_limites_uso_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `utmfy_integrations`
--
ALTER TABLE `utmfy_integrations`
  ADD CONSTRAINT `fk_utmfy_integrations_produto` FOREIGN KEY (`product_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_utmfy_integrations_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `vendas`
--
ALTER TABLE `vendas`
  ADD CONSTRAINT `fk_vendas_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `webhooks`
--
ALTER TABLE `webhooks`
  ADD CONSTRAINT `fk_webhooks_produto` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_webhooks_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
