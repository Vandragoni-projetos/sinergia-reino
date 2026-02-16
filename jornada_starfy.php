<?php
// Inclui o arquivo de configuração que inicia a sessão e a conexão PDO
require_once __DIR__ . '/config/config.php';

// Proteção de página: verifica se o usuário está logado.
// A lógica em 'index.php' já garante que usuários logados que chegam aqui não são administradores.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /login");
    exit;
}
?>

<div class="container mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Sua Jornada GatewayPro</h1>

    <!-- O container principal (Estágio Atual) permanece o mesmo, pois já está ótimo! -->
    <div id="jornada-GatewayPro-container" class="bg-gradient-to-br from-purple-800 via-indigo-900 to-black p-6 rounded-lg shadow-md mb-8 text-white relative overflow-hidden transition-colors duration-1000 ease-in-out">
        <!-- Imagem de nebulosa semitransparente -->
        <div class="absolute inset-0 opacity-30" style="background-image: url('https://static.vecteezy.com/ti/fotos-gratis/p1/2097706-3d-realistic-nebula-space-background-gratis-foto.jpg'); background-size: cover; background-position: center; pointer-events: none;"></div>
        
        <div class="relative z-10">
            <h2 class="text-3xl font-extrabold text-white mb-4">Seu Caminho Estelar no GatewayPro</h2>
            <p class="text-gray-200 mb-6">Acompanhe seu progresso e celebre suas conquistas na plataforma!</p>

            <div class="flex items-center space-x-4 mb-2">
                <i data-lucide="star" class="w-8 h-8 text-yellow-400 flex-shrink-0 animate-pulse"></i>
                <div>
                    <p class="text-lg font-bold" id="journey-stage-name">Carregando Etapa...</p>
                    <p class="text-sm text-gray-300" id="journey-stage-description">...</p>
                </div>
            </div>

            <div class="mt-6">
                <div class="w-full bg-white rounded-full h-3.5 mb-2 relative">
                    <div id="journey-progress-bar-fill" class="bg-green-500 h-3.5 rounded-full transition-all duration-700 ease-out" style="width: 0%;"></div>
                    <span id="journey-progress-text" class="absolute inset-0 flex items-center justify-center text-xs font-semibold text-gray-900 opacity-90">0%</span>
                </div>
                <div class="flex justify-between text-sm mt-1">
                    <span id="journey-current-amount" class="font-medium text-gray-200">R$ 0,00</span>
                    <span id="journey-next-amount" class="font-bold text-white">R$ 0,00</span>
                </div>
            </div>
            <p id="journey-next-stage-message" class="text-xs text-gray-400 mt-2 text-right">Faltam R$ 0,00 para a próxima etapa!</p>
        </div>
    </div>

    <!-- Seção da Linha do Tempo da Jornada -->
    <div class="bg-white p-6 rounded-lg shadow-md mt-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Sua Trilha de Conquistas</h2>
        <p class="text-gray-600 mb-6">Veja onde você está e quais são seus próximos passos no universo GatewayPro.</p>

        <!-- O container da timeline. As classes de grid foram removidas. -->
        <div id="jornada-timeline-container" class="mt-6 space-y-8">
            <!-- As etapas da timeline serão preenchidas aqui via JavaScript -->
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mapeamento das etapas da jornada GatewayPro (sem alteração)
    const GatewayPro_STAGES = [
        { name: 'ESTRELA CADENTE', min: 0, max: 10000, description: 'Toda grande jornada começa com um vislumbre. Você acendeu a sua luz no universo GatewayPro. É o ponto de partida de uma trajetória que promete ser épica.', color_classes: 'from-purple-800 via-indigo-900 to-black' },
        { name: 'NEBULOSA ASCENDENTE', min: 10000, max: 100000, description: 'Uma formação se inicia, cores vibrantes e um brilho crescente. Você está moldando seu espaço na galáxia GatewayPro, com potencial para se expandir e impressionar.', color_classes: 'from-indigo-900 via-blue-950 to-gray-900' },
        { name: 'SISTEMA SOLAR', min: 100000, max: 250000, description: 'Você estabeleceu um centro de gravidade, atraindo e energizando. Seu sistema está em equilíbrio, mostrando que a sua órbita é consistente e promissora.', color_classes: 'from-blue-800 via-sky-950 to-slate-900' },
        { name: 'AGLOMERADO ESTELAR', min: 250000, max: 500000, description: 'Seu brilho não está sozinho; você se une a outros astros, formando uma força coesa. Sua presença se torna notável, um grupo de estrelas que ecoam no universo.', color_classes: 'from-cyan-700 via-teal-900 to-zinc-950' },
        { name: 'SUPERNOVA DOURADA', min: 500000, max: 1000000, description: 'Um evento cósmico de proporções grandiosas! Sua energia explode, iluminando vastas extensões. Você é uma força que redefine o seu entorno, uma referência inconfundível.', color_classes: 'from-yellow-400 via-amber-600 to-green-700' },
        { name: 'GALÁXIA SUPREMA', min: 1000000, max: 5000000, description: 'Você se tornou um universo em si, com trilhões de estrelas e uma complexidade que inspira. Uma galáxia construída com visão, resiliência e paixão, reconhecida por sua vastidão.', color_classes: 'from-green-400 via-red-600 to-rose-700' },
        { name: 'CONSTELAÇÃO ETERNA', min: 5000000, max: 10000000, description: 'Sua luz transcende o tempo, formando um padrão que será lembrado por gerações. Um legado gravado no firmamento, um marco de excelência e inspiração.', color_classes: 'from-pink-400 via-fuchsia-600 to-purple-700' },
        { name: 'BURACO NEGRO DE SUCESSO', min: 10000000, max: Infinity, description: 'O ápice, onde a gravidade do seu sucesso é irresistível! Você não apenas atravessou o universo, você se tornou a força dominante que atrai e molda tudo ao seu redor. Esta é a mais alta honra da GatewayPro, reservada aos que redefiniram os limites.', color_classes: 'from-gray-700 via-gray-800 to-black' }
    ];

    function formatCurrency(value) {
        return parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    async function fetchJornadaGatewayProData() {
        try {
            const response = await fetch('api.php?action=get_jornada_GatewayPro_data');
            if (!response.ok) {
                throw new Error('Erro ao buscar dados da jornada.');
            }
            const data = await response.json();
            if (data.success === false) {
                throw new Error(data.error || 'Erro desconhecido da API.');
            }
            renderGatewayProJourney(data.total_faturamento_lifetime);
            // Passa o faturamento para a função de renderizar a timeline também
            renderAllStagesTimeline(data.total_faturamento_lifetime);
        } catch (error) {
            console.error('Falha ao carregar a jornada GatewayPro:', error);
            document.getElementById('journey-stage-name').textContent = 'Erro ao Carregar';
            document.getElementById('journey-stage-description').textContent = 'Tente recarregar a página.';
            document.getElementById('journey-progress-text').textContent = '0%';
            document.getElementById('jornada-GatewayPro-container').classList.add('from-red-600', 'via-red-700', 'to-red-800');
        }
    }

    function renderGatewayProJourney(totalFaturamentoLifetime) {
        // Esta função permanece a mesma, cuidando do box principal
        let currentStage = null;
        for (let i = 0; i < GatewayPro_STAGES.length; i++) {
            if (totalFaturamentoLifetime >= GatewayPro_STAGES[i].min && totalFaturamentoLifetime < GatewayPro_STAGES[i].max) {
                currentStage = GatewayPro_STAGES[i];
                break;
            }
        }
        if (!currentStage && totalFaturamentoLifetime >= GatewayPro_STAGES[GatewayPro_STAGES.length - 1].min) {
            currentStage = GatewayPro_STAGES[GatewayPro_STAGES.length - 1];
        } else if (!currentStage) { 
             currentStage = GatewayPro_STAGES[0];
        }

        if (currentStage) {
            document.getElementById('journey-stage-name').textContent = currentStage.name;
            document.getElementById('journey-stage-description').textContent = currentStage.description;
            
            const journeyContainer = document.getElementById('jornada-GatewayPro-container');
            const currentClasses = Array.from(journeyContainer.classList);
            const classesToRemove = currentClasses.filter(cls => cls.startsWith('from-') || cls.startsWith('via-') || cls.startsWith('to-'));
            journeyContainer.classList.remove(...classesToRemove);
            journeyContainer.classList.add(...currentStage.color_classes.split(' '));

            if (currentStage.max === Infinity) { 
                document.getElementById('journey-current-amount').textContent = formatCurrency(totalFaturamentoLifetime);
                document.getElementById('journey-next-amount').textContent = 'Conquista Máxima!';
                document.getElementById('journey-progress-bar-fill').style.width = '100%';
                document.getElementById('journey-progress-text').textContent = '100%';
                document.getElementById('journey-next-stage-message').textContent = 'Você alcançou o ápice da Jornada GatewayPro!';
            } else {
                let currentAmountInStage = totalFaturamentoLifetime - currentStage.min;
                let amountNeededForStage = currentStage.max - currentStage.min;
                let progressPercent = 0;
                
                if (amountNeededForStage > 0) {
                    progressPercent = Math.min(100, (currentAmountInStage / amountNeededForStage) * 100);
                } else {
                    progressPercent = 100;
                }
                
                document.getElementById('journey-current-amount').textContent = formatCurrency(totalFaturamentoLifetime);
                document.getElementById('journey-next-amount').textContent = formatCurrency(currentStage.max);
                document.getElementById('journey-progress-bar-fill').style.width = `${progressPercent.toFixed(2)}%`;
                document.getElementById('journey-progress-text').textContent = `${progressPercent.toFixed(0)}%`;
                
                const remaining = currentStage.max - totalFaturamentoLifetime;
                document.getElementById('journey-next-stage-message').textContent = `Faltam ${formatCurrency(remaining)} para a próxima etapa!`;
            }
        }
        // A chamada para renderAllStages foi movida para fetchJornadaGatewayProData
    }

    /**
     * NOVA FUNÇÃO: renderAllStagesTimeline
     * Esta função substitui a 'renderAllStages' e constrói a linha do tempo vertical.
     */
    function renderAllStagesTimeline(currentFaturamento) {
        const timelineContainer = document.getElementById('jornada-timeline-container');
        if (!timelineContainer) return; // Garante que o container existe
        
        timelineContainer.innerHTML = ''; // Limpa o container

        GatewayPro_STAGES.forEach((stage, index) => {
            let status = 'locked';
            let iconName = 'lock';
            let iconClasses = 'text-gray-400';
            let dotClasses = 'bg-gray-200 border-gray-300';
            let cardClasses = 'bg-gray-100 border-gray-200';
            let textClasses = 'text-gray-500';

            if (currentFaturamento >= stage.min && currentFaturamento < stage.max) {
                // Estágio ATUAL
                status = 'current';
                iconName = 'star';
                iconClasses = 'text-green-500 animate-pulse';
                dotClasses = 'bg-green-100 border-green-500';
                cardClasses = 'bg-white border-green-500 shadow-md shadow-green-100';
                textClasses = 'text-gray-800';
            } else if (currentFaturamento >= stage.max) {
                // Estágio COMPLETO
                status = 'complete';
                iconName = 'check';
                iconClasses = 'text-green-600';
                dotClasses = 'bg-green-100 border-green-500';
                cardClasses = 'bg-green-50 border-green-200 opacity-90'; // Leve opacidade para dar foco ao atual
                textClasses = 'text-green-800';
            }
            // 'locked' já é o padrão

            const item = document.createElement('div');
            item.className = 'relative flex items-start';

            // Adiciona a linha conectora, exceto no último item
            if (index < GatewayPro_STAGES.length - 1) {
                item.innerHTML = '<div class="absolute left-4 top-4 -ml-px w-0.5 h-full bg-gray-300"></div>';
            }

            // O Ponto (dot) na timeline com o ícone
            const dot = document.createElement('div');
            dot.className = `relative z-10 flex items-center justify-center w-8 h-8 rounded-full border-2 ${dotClasses} flex-shrink-0`;
            dot.innerHTML = `<i data-lucide="${iconName}" class="w-5 h-5 ${iconClasses}"></i>`;

            // O Card com o conteúdo da etapa
            const card = document.createElement('div');
            card.className = `ml-6 w-full p-4 rounded-lg border ${cardClasses} transition-all`;
            
            let amountText = `A partir de: ${formatCurrency(stage.min)}`;
            if (stage.max !== Infinity) {
                amountText += ` (até ${formatCurrency(stage.max)})`;
            } else {
                amountText += ` (Nível Máximo)`;
            }

            card.innerHTML = `
                <h3 class="font-bold text-lg ${textClasses}">${stage.name}</h3>
                <p class="text-sm text-gray-600 mt-1">${stage.description}</p>
                <p class="text-sm font-semibold mt-3 ${textClasses}">${amountText}</p>
            `;

            // Monta o item
            item.appendChild(dot);
            item.appendChild(card);
            
            // Adiciona o item completo ao container
            timelineContainer.appendChild(item);
        });

        lucide.createIcons(); // Renderiza os ícones Lucide para os novos elementos
    }

    // Chama a função para buscar e renderizar os dados da jornada
    fetchJornadaGatewayProData();
});
</script>