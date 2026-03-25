

**Sistema Inteligente Multirriscos -- Defesa Civil do Estado do Pará
(CEDEC/PA)**

**Paula Fernanda Correa Lima -- Eng. Software**

Responsável Técnica do Projeto de Software

Belém-PA

2026

## **ÍNDICE**

1.  Introdução\
    1.1 Proposta\
    1.2 Escopo\
    1.3 Resumo / Visão Geral\
    1.4 Referencial Teórico\
    1.5 Definições e Acrônimos

2.  Visão Geral do Sistema

3.  Arquitetura do Sistema\
    3.1 Projeto Arquitetural\
    3.2 Descrição da Decomposição\
    3.3 Projeto Racional

4.  Projeto de Dados\
    4.1 Descrição de Dados\
    4.2 Dicionário de Dados

5.  Projeto de Componentes

6.  Projeto de Interface\
    6.1 Visão Geral da Interface\
    6.2 Telas\
    6.3 Ações e Objetos

7.  Matriz de Requisitos

8.  Apêndice

9.  Ajustes e Manutenção

10. Referências Bibliográficas

## **1. INTRODUÇÃO**

### 1.1 Proposta

Este documento tem como finalidade especificar, de forma estruturada e
padronizada, o Projeto de Software do **Sistema Inteligente
Multirriscos**, desenvolvido para a Coordenadoria Estadual de Proteção e
Defesa Civil do Estado do Pará (CEDEC/PA). O documento estabelece a base
técnica e conceitual do sistema, descrevendo sua arquitetura,
componentes, dados, interfaces e requisitos, de modo a garantir clareza,
rastreabilidade, manutenção e evolução futura.

### 1.2 Escopo

O escopo do **Sistema Inteligente Multirriscos** compreende o
desenvolvimento, implantação e manutenção de uma plataforma web
corporativa destinada ao **monitoramento, integração, gestão, análise e
divulgação de alertas multirriscos**, com base territorial, voltada ao
apoio à tomada de decisão da Defesa Civil do Estado do Pará.

O sistema abrange:

-   Integração de dados provenientes de **múltiplas fontes oficiais e
    institucionais**, incluindo INMET, ANA, CENSIPAM, Marinha do Brasil,
    SIMD, CEMADEN, Serviço Geológico do Brasil (SGB) e SEMAS;

-   Consolidação de informações meteorológicas, hidrológicas,
    geológicas, ambientais e oceânicas em uma única plataforma;

-   Associação automática de alertas a municípios e Regiões de
    Integração do Estado do Pará, com base em processamento geoespacial;

-   Visualização cartográfica interativa dos alertas e áreas afetadas;

-   Emissão, acompanhamento e histórico de alertas multirriscos;

-   Geração de relatórios técnicos oficiais, com identidade visual
    institucional e validade administrativa;

-   Exportação de dados em formatos geográficos e tabulares (KML, CSV e
    outros);

-   Disponibilização de análises estatísticas, temporais e territoriais
    para apoio estratégico, tático e operacional;

-   Controle de acesso baseado em perfis de usuário (RBAC), garantindo
    segurança e segregação de responsabilidades.

Não fazem parte do escopo deste sistema:

-   A substituição dos sistemas oficiais de origem das informações;

-   A execução direta de ações de resposta ou defesa civil em campo;

-   A gestão de recursos humanos, materiais ou logísticos externos ao
    sistema;

-   A tomada de decisão automática sem validação humana;

-   A administração de infraestrutura física de servidores e redes.

O Sistema Inteligente Multirriscos posiciona-se, portanto, como uma
**plataforma integradora e analítica**, destinada a consolidar
informações críticas de risco, fortalecer a governança de dados e
qualificar o processo decisório da Defesa Civil do Estado do Pará.

### 1.3 Resumo / Visão Geral

O **Sistema Inteligente Multirriscos** é um sistema web corporativo
voltado ao **monitoramento, gestão, análise, integração e divulgação de
alertas multirriscos**, com base territorial, destinado a subsidiar a
**tomada de decisão estratégica, tática e operacional** da Defesa Civil
do Estado do Pará.

O sistema integra dados provenientes de **múltiplas fontes oficiais e
institucionais**, incluindo, mas não se limitando a:

-   Instituto Nacional de Meteorologia (INMET);

-   Agência Nacional de Águas e Saneamento Básico (ANA);

-   Centro Gestor e Operacional do Sistema de Proteção da Amazônia
    (CENSIPAM);

-   Marinha do Brasil;

-   Subdivisão de Informações de Monitoramento de Desastres (SIMD);

-   Centro Nacional de Monitoramento e Alertas de Desastres Naturais
    (CEMADEN);

-   Serviço Geológico do Brasil (SGB);

-   Secretaria de Estado de Meio Ambiente e Sustentabilidade (SEMAS).

Além da integração de dados oficiais, o sistema incorpora **informações
geoespaciais dos municípios e das Regiões de Integração do Estado do
Pará**, permitindo a análise territorial precisa dos eventos de risco.

O Sistema Inteligente Multirriscos disponibiliza recursos avançados de
**visualização cartográfica, análises temporais e estatísticas, geração
de relatórios técnicos oficiais, emissão e acompanhamento de alertas**,
bem como exportação de dados geográficos e tabulares, assegurando
rastreabilidade, padronização institucional e confiabilidade das
informações.

Dessa forma, o sistema constitui uma **plataforma integrada de
inteligência multirriscos**, capaz de apoiar ações de prevenção,
mitigação, preparação, resposta e recuperação, em consonância com a
Política Nacional de Proteção e Defesa Civil e com as necessidades
operacionais do Estado do Pará.

### 1.4 Referencial Teórico

O desenvolvimento do **Sistema Inteligente Multirriscos** está
fundamentado em referenciais legais, normativos, técnicos e conceituais
que orientam tanto a concepção do software quanto sua aplicação no
contexto da Proteção e Defesa Civil, da engenharia de software e do
geoprocessamento.

Os principais referenciais adotados são:

-   **Lei nº 12.608/2012** -- Institui a Política Nacional de Proteção e
    Defesa Civil (PNPDEC), estabelecendo diretrizes para prevenção,
    mitigação, preparação, resposta e recuperação frente a desastres;

-   **Decreto nº 10.593/2020** -- Dispõe sobre a organização e o
    funcionamento do Sistema Nacional de Proteção e Defesa Civil
    (SINPDEC);

-   **Marco de Sendai para Redução do Risco de Desastres (2015--2030)**
    -- Referência internacional para gestão de riscos e desastres,
    enfatizando o uso de informação, tecnologia e análise de risco;

-   **ISO/IEC 25010** -- Modelo de Qualidade de Produto de Software,
    adotado para garantir atributos como confiabilidade, segurança,
    usabilidade, manutenibilidade e eficiência de desempenho;

-   **ISO/IEC 12207** -- Processos do ciclo de vida de software,
    orientando práticas de desenvolvimento, manutenção e evolução do
    sistema;

-   **IEEE 830 / IEEE 29148** -- Padrões para especificação de
    requisitos de software, assegurando clareza, rastreabilidade e
    validação dos requisitos funcionais e não funcionais;

-   **OWASP (Open Worldwide Application Security Project)** -- Boas
    práticas de segurança para aplicações web, utilizadas como
    referência para controle de acesso, proteção de dados e mitigação de
    vulnerabilidades;

-   **Princípios de Controle de Acesso Baseado em Papéis (RBAC)** --
    Fundamentados em modelos clássicos de segurança da informação para
    segregação de responsabilidades e proteção de funcionalidades
    sensíveis;

-   **Padrões OGC (Open Geospatial Consortium)** -- Conjunto de
    especificações para dados e serviços geoespaciais, garantindo
    interoperabilidade e padronização na representação territorial;

-   **ISO 19115 e ISO 19107** -- Normas para metadados geográficos e
    modelagem espacial, aplicáveis à organização e consistência das
    informações territoriais;

-   **Princípios de Sistemas de Informação Geográfica (SIG)** --
    Aplicados ao processamento, análise e visualização de dados
    espaciais no contexto de riscos e desastres;

-   **Arquitetura de Software em Camadas** -- Adotada para separação de
    responsabilidades, manutenibilidade e escalabilidade do sistema;

-   **Padrão MVC (Model--View--Controller)** -- Utilizado para organizar
    a lógica de negócio, a camada de apresentação e o controle de fluxo
    da aplicação;

-   **Princípios de Governança de Dados** -- Fundamentados em boas
    práticas de gestão, qualidade, rastreabilidade e confiabilidade da
    informação em sistemas públicos;

-   **Literatura sobre Sistemas de Apoio à Decisão (Decision Support
    Systems -- DSS)** -- Aplicada ao uso de análises, indicadores e
    visualizações para suporte à decisão em contextos complexos e
    críticos;

-   **Estudos e diretrizes técnicas da Defesa Civil e de órgãos
    federais** (CEMADEN, ANA, INMET, SGB e CENSIPAM), que orientam o uso
    e interpretação de dados oficiais de monitoramento e alerta.

Este conjunto de referenciais assegura que o Sistema Inteligente
Multirriscos esteja fundamentado em **bases legais sólidas**, **normas
técnicas reconhecidas**, **boas práticas de engenharia de software** e
**conceitos consolidados de gestão de riscos e desastres**, garantindo
sua confiabilidade, legitimidade institucional e capacidade de evolução.

### 1.5 Definições e Acrônimos

  --------------------------------------------------------------------------
  **Termo**    **Definição**
  ------------ -------------------------------------------------------------
  CEDEC        Coordenadoria Estadual de Proteção e Defesa Civil do Estado
               do Pará

  INMET        Instituto Nacional de Meteorologia

  ANA          Agência Nacional de Águas e Saneamento Básico

  CENSIPAM     Centro Gestor e Operacional do Sistema de Proteção da
               Amazônia

  Marinha      Marinha do Brasil, responsável por informações oceânicas,
               costeiras e hidrometeorológicas

  SIMD         Sistema Integrado de Monitoramento e Alerta da Defesa Civil

  CEMADEN      Centro Nacional de Monitoramento e Alertas de Desastres
               Naturais

  SGB          Serviço Geológico do Brasil

  SEMAS        Secretaria de Estado de Meio Ambiente e Sustentabilidade

  RBAC         *Role-Based Access Control* -- Modelo de controle de acesso
               baseado em papéis

  IRP          Índice de Pressão de Risco -- indicador analítico que mede a
               pressão operacional causada por alertas, considerando
               severidade e abrangência territorial

  IPT          Índice de Pressão Territorial -- indicador que mensura a
               pressão acumulada sobre municípios específicos

  GeoJSON      Formato padrão aberto para representação de dados
               geoespaciais baseado em JSON

  JSON         *JavaScript Object Notation* -- formato leve de intercâmbio
               de dados estruturados

  KML          *Keyhole Markup Language* -- formato XML utilizado para
               visualização de dados geográficos

  MVC          *Model-View-Controller* -- padrão arquitetural que separa
               dados, interface e lógica de controle

  HTML         *HyperText Markup Language* -- linguagem de marcação
               utilizada na estruturação de páginas web

  CSS3         *Cascading Style Sheets -- versão 3* -- linguagem utilizada
               para definição de estilos visuais em páginas web

  JavaScript   Linguagem de programação utilizada para interatividade e
               comportamento dinâmico na interface web

  PHP          *Hypertext Preprocessor* -- linguagem de programação
               utilizada no backend do sistema

  MySQL        Sistema Gerenciador de Banco de Dados relacional utilizado
               para persistência dos dados

  MariaDB      Sistema Gerenciador de Banco de Dados relacional compatível
               com MySQL

  PDF          *Portable Document Format* -- formato de arquivo utilizado
               para documentos técnicos oficiais

  CSV          *Comma-Separated Values* -- formato de arquivo tabular para
               exportação de dados

  API          *Application Programming Interface* -- interface que permite
               integração entre sistemas

  UML          *Unified Modeling Language* -- linguagem padrão para
               modelagem de sistemas de software

  SQL          *Structured Query Language* -- linguagem padrão para consulta
               e manipulação de bancos de dados relacionais
  --------------------------------------------------------------------------

## **2. VISÃO GERAL DO SISTEMA**

O Sistema Inteligente Multirriscos foi concebido para centralizar
informações sobre alertas de risco, associando dados temporais,
espaciais e analíticos em uma única plataforma. Seu objetivo principal é
fornecer uma visão integrada e confiável da situação de risco no
território paraense, permitindo análise histórica, acompanhamento em
tempo real e apoio à decisão operacional.

## **3. ARQUITETURA DO SISTEMA**

### 3.1 Projeto Arquitetural

O sistema adota uma arquitetura em camadas, com padrão MVC adaptado,
composta por:

-   **Camada de Apresentação:** Interface web desenvolvida em HTML5,
    CSS3 e JavaScript.
-   **Camada de Controle:** Controladores PHP responsáveis pelo fluxo
    das requisições;
-   **Camada de Serviços:** Serviços especializados para regras de
    negócio e geoprocessamento;
-   **Camada de Persistência:** Banco de dados relacional MySQL/MariaDB.

### 3.2 Descrição da Decomposição

O sistema está organizado em módulos funcionais, tais como:

-   Módulo de Alertas;
-   Módulo de Mapas Multirriscos;
-   Módulo de Análises.
-   Módulo de Usuários e Controle de Acesso;
-   Módulo de Relatórios (PDF, CSV, KML).

Cada módulo é composto por páginas, serviços e recursos específicos,
mantendo baixo acoplamento e alta coesão.

### 3.3 Projeto Racional

As decisões arquiteturais priorizaram:

-   Manutenibilidade;
-   Escalabilidade;
-   Auditabilidade;
-   Conformidade com normas governamentais;
-   Independência de APIs externas para processamento crítico.

## **4. PROJETO DE DADOS**

O Projeto de Dados define a estrutura lógica e conceitual do banco de
dados do Sistema Inteligente Multirriscos, estabelecendo entidades,
relacionamentos, atributos e regras de integridade. Este projeto visa
garantir consistência, rastreabilidade, desempenho analítico e
confiabilidade operacional.

### 4.1 Descrição de Dados

O sistema utiliza um banco de dados relacional (MySQL/MariaDB),
estruturado segundo os princípios da normalização, com separação clara
entre:

-   **Dados Operacionais**: utilizados no funcionamento diário do
    sistema;

-   **Dados Territoriais**: responsáveis pela associação espacial;

-   **Dados Analíticos**: utilizados para geração de indicadores e
    análises.

As entidades centrais do modelo são:

-   alertas

-   alerta_municipios

-   alerta_regioes

-   municipios_regioes_pa

-   usuarios

O alerta é a unidade primária de análise, sendo registrado uma única
vez, enquanto seus impactos territoriais são tratados em tabelas de
relacionamento.

4.2 Dicionário de Dados

### 4.2.1 Tabela: alertas

  -----------------------------------------------------------------------------
  **Campo**         **Tipo de      **Descrição**               **Regra /
                    Dados**                                    Observação**
  ----------------- -------------- --------------------------- ----------------
  id                INT (PK)       Identificador único do      Autoincremento
                                   alerta                      

  numero            VARCHAR        Número oficial do alerta    Único

  fonte             VARCHAR        Origem do alerta (INMET,    Obrigatório
                                   MANUAL)                     

  tipo_evento       VARCHAR        Tipo do fenômeno            Obrigatório

  nivel_gravidade   ENUM           Grau de severidade          Obrigatório

  data_alerta       DATETIME       Data de emissão do alerta   Obrigatório

  inicio_alerta     DATETIME       Início da vigência do       Obrigatório
                                   alerta                      

  fim_alerta        DATETIME       Fim da vigência do alerta   Obrigatório

  riscos            TEXT           Riscos potenciais           Opcional
                                   associados ao alerta        

  recomendacoes     TEXT           Ações recomendadas          Opcional

  area_geojson      LONGTEXT       Polígono geográfico do      Obrigatório
                                   alerta                      

  informacoes       VARCHAR        Caminho da imagem           Opcional
                                   complementar                

  criado_em         DATETIME       Auditoria de criação do     Automático
                                   registro                    
  -----------------------------------------------------------------------------

### 4.2.2 Tabela: alerta_municipios

  ----------------------------------------------------------------------------
  **Campo**          **Tipo de Dados** **Descrição**             **Regra**
  ------------------ ----------------- ------------------------- -------------
  alerta_id          INT (FK)          Referência ao alerta      Obrigatório
                                       associado                 

  municipio_codigo   INT               Código IBGE do município  Obrigatório

  municipio_nome     VARCHAR           Nome do município         Obrigatório
  ----------------------------------------------------------------------------

**Relacionamento: N alertas para N municípios.**

**Observações técnicas:**

-   alerta_id referência a chave primária da tabela alertas.

-   Recomenda-se **chave primária composta** (alerta_id,
    municipio_codigo) para evitar duplicidade.

-   Pode-se aplicar **índice** em municipio_codigo para otimizar
    consultas por município.

-   Integrável a análises espaciais e relatórios territoriais.

### 4.2.3 Tabela: Tabela: alerta_regioes

  ------------------------------------------------------------------------------
  **Campo**           **Tipo de       **Descrição**                **Regra**
                      Dados**                                      
  ------------------- --------------- ---------------------------- -------------
  alerta_id           INT (FK)        Referência ao alerta         Obrigatório
                                      associado                    

  regiao_integracao   VARCHAR         Nome da região de integração Obrigatório
  ------------------------------------------------------------------------------

**Relacionamento: Derivado dos municípios associados ao alerta.**

**Observações técnicas:**

-   alerta_id referência a chave primária da tabela alertas.

-   Recomenda-se **chave primária composta** (alerta_id,
    regiao_integracao) para evitar duplicidades.

-   Pode-se aplicar **índice** em regiao_integracao para otimizar
    consultas regionais.

-   Estrutura adequada para **análises territoriais agregadas** e
    relatórios estratégicos.

### 4.2.4 Tabela: municipios_regioes_pa

  -----------------------------------------------------------------------------
  **Campo**           **Tipo de Dados** **Descrição**             **Regra**
  ------------------- ----------------- ------------------------- -------------
  cod_ibge            INT (PK)          Código IBGE do município  Único

  municipio           VARCHAR           Nome do município         Obrigatório

  regiao_integracao   VARCHAR           Região de Integração      Obrigatório
  -----------------------------------------------------------------------------

**Tabela de referência territorial oficial do Estado do Pará.**

**Observações técnicas:**

-   cod_ibge é a **chave primária** da tabela.

-   A coluna regiao_integracao permite **agregações regionais** e
    análises estratégicas.

-   Recomenda-se **índice** adicional em regiao_integracao para
    otimização de consultas.

-   Tabela de **domínio territorial**, utilizada como referência por
    tabelas de relacionamento (alertas_municipios).

### 4.2.5 Tabela: usuários

  ------------------------------------------------------------------------------
  **Campo**    **Tipo de       **Descrição**                    **Regra**
               Dados**                                          
  ------------ --------------- -------------------------------- ----------------
  id           INT (PK)        Identificador único do usuário   Autoincremento

  nome         VARCHAR         Nome completo do usuário         Obrigatório

  email        VARCHAR         Email institucional              Único

  senha_hash   VARCHAR         Hash da senha do usuário         Obrigatório

  perfil       ENUM            Perfil de acesso (RBAC)          Obrigatório

  status       ENUM            Situação do usuário              Obrigatório
                               (ATIVO/INATIVO)                  

  criado_em    DATETIME        Data de criação do registro      Automático
  ------------------------------------------------------------------------------

**Observações técnicas:**

-   O campo perfil implementa o **controle de acesso baseado em papéis
    (RBAC)**, conforme RF-02.

-   O campo senha_hash deve armazenar apenas **hash seguro** (ex.:
    bcrypt/Argon2).

-   Recomenda-se **índice único** em email para garantir unicidade e
    otimizar autenticação.

-   O campo status permite **bloqueio lógico** sem exclusão física do
    usuário.

-   Tabela fundamental para **auditoria, segurança e rastreabilidade**
    do sistema.

### 4.3 Relacionamentos e Integridade

-   alertas (1) → alerta_municipios (N)

-   alerta_municipios (N) → municipios_regioes_pa (1)

-   alertas (1) → alerta_regioes (N)

Regras:

-   Um alerta é contado uma única vez nas análises;

-   Municípios e regiões são sempre derivados do polígono geográfico;

-   Exclusão ou edição de área exige reprocessamento territorial
    completo.

### 4.4 Considerações Analíticas

Para evitar inflar estatísticas, o sistema adota:

-   COUNT(DISTINCT alerta_id);

-   Separação entre evento (alerta) e impacto (município);

-   Uso de pesos de severidade padronizados.

\-\--\|\-\-\-\-\--\|\-\-\-\-\-\-\-\-\--\| \| id \| INT \| Identificador
único do alerta \| \| tipo_evento \| VARCHAR \| Tipo do fenômeno \| \|
nivel_gravidade \| ENUM \| Grau de severidade \| \| inicio_alerta \|
DATETIME \| Início da vigência \| \| fim_alerta \| DATETIME \| Fim da
vigência \|

## **5. PROJETO DE COMPONENTES**

O Projeto de Componentes descreve os principais componentes de software
do Sistema Inteligente Multirriscos, detalhando suas responsabilidades,
interfaces, dependências e decisões de projeto. Este nível de
especificação garante compreensão técnica, manutenção adequada e
evolução controlada do sistema.

**5.1 Visão Geral dos Componentes**

O sistema é estruturado em componentes lógicos, organizados
principalmente nas camadas **Core**, **Services** e **Pages
(Controladores)**. Cada componente possui responsabilidade única,
evitando acoplamento excessivo e duplicação de regras de negócio.

**5.2** Componentes da Camada Core

**5.2.1** Database.php

**Responsabilidade:**

-   Centralizar a conexão com o banco de dados;

-   Garantir uso padronizado de PDO;

-   Facilitar manutenção e segurança da persistência.

**Principais funções:**

-   Criação da instância PDO;

-   Configuração de charset e modo de erro;

**Decisão de projeto:** A centralização da conexão evita múltiplas
implementações inconsistentes e facilita futuras alterações de
infraestrutura.

**5.2.2** Protect.php

**Responsabilidade:**

-   Gerenciar sessão do usuário;

-   Validar autenticação;

-   Controlar acesso por perfil (RBAC).

**Principais funções:**

-   Verificação de sessão ativa;

-   Validação de perfil autorizado;

-   Bloqueio de acesso não autorizado.

**Decisão de projeto:** A implementação centralizada do RBAC aplica o
princípio do menor privilégio e garante segurança transversal em todo o
sistema.

**5.3** Componentes da Camada de Serviços

**5.3.1** InmetService.php

**Responsabilidade:**

-   Consumir dados oficiais do INMET;

-   Realizar parsing de conteúdo externo;

-   Converter dados para o modelo interno do sistema.

**Principais funções:**

-   Leitura de URL externa;

-   Extração de metadados do alerta;

-   Converter os dados obtidos para o modelo interno padronizado do
    sistema.

**5.3.2** TerritorioService.php

**Responsabilidade**

O componente **TerritorioService.php** é responsável por executar o
processamento geoespacial central do Sistema Inteligente Multirriscos,
constituindo o **núcleo estratégico de inteligência territorial** da
aplicação. Sua função é transformar a área geográfica de um alerta em
informação territorial estruturada, confiável e auditável, permitindo a
correta identificação dos municípios e das Regiões de Integração
afetadas.

Compete a este serviço:

-   Processar dados geoespaciais associados aos alertas;

-   Identificar automaticamente os municípios impactados;

-   Determinar as Regiões de Integração correspondentes;

-   Garantir consistência territorial entre mapas, banco de dados,
    análises e relatórios.

**Principais Funções**

O TerritorioService executa as seguintes funções técnicas:

-   Leitura da malha municipal oficial do Estado do Pará em formato
    GeoJSON;

-   Cálculo de *bounding box* do polígono do alerta, com finalidade de
    otimização computacional;

-   Verificação de interseção espacial entre o polígono do alerta e os
    polígonos municipais;

-   Identificação dos municípios efetivamente afetados pelo alerta;

-   Associação de cada município à sua respectiva Região de Integração;

-   Consolidação das informações territoriais em estruturas de dados
    internas;

-   Persistência controlada dos resultados nas tabelas:

    -   alerta_municipios

    -   alerta_regioes

**Entradas**

-   Polígono geográfico do alerta em formato GeoJSON;

-   Base geoespacial oficial dos municípios do Estado do Pará;

-   Tabela de referência territorial municipios_regioes_pa.

**Saídas**

-   Lista estruturada de municípios afetados pelo alerta;

-   Lista consolidada de Regiões de Integração impactadas;

-   Registros territoriais persistidos no banco de dados, garantindo
    rastreabilidade e integridade.

**Dependências**

-   Arquivo GeoJSON da malha municipal oficial do Estado do Pará;

-   Classe de persistência Database.php;

-   Tabela de referência municipios_regioes_pa.

**Decisão de Projeto**

O processamento territorial foi projetado para operar **integralmente de
forma offline**, sem dependência de serviços externos ou APIs de
terceiros. Essa decisão arquitetural garante:

-   Determinismo dos resultados geoespaciais;

-   Reprodutibilidade das análises territoriais;

-   Auditabilidade técnica e institucional;

-   Independência operacional do sistema;

-   Maior controle sobre a integridade dos dados.

Sempre que a área geográfica de um alerta é criada ou alterada, o
sistema executa o **reprocessamento territorial completo**, recalculando
municípios e regiões afetadas. Essa abordagem assegura total coerência
entre o polígono visualizado no mapa, os dados armazenados no banco, os
indicadores analíticos e os relatórios oficiais gerados.

**5.3.3** PdfService.php

**Responsabilidade**

O componente **PdfService.php** é responsável pela geração dos
**relatórios técnicos oficiais** do Sistema Inteligente Multirriscos,
consolidando informações operacionais, territoriais e visuais em
documentos formais, padronizados e institucionalmente válidos.

Este serviço tem como finalidade assegurar que os relatórios emitidos
pelo sistema possam ser utilizados como **instrumentos oficiais de
comunicação, registro e tomada de decisão**, atendendo às exigências
técnicas, administrativas e legais da Defesa Civil do Estado do Pará.

**Principais Funções**

O PdfService executa as seguintes funções:

-   Recuperar os dados completos do alerta a partir da base de dados;

-   Recuperar a relação de municípios e Regiões de Integração afetadas;

-   Incorporar a imagem real do mapa do alerta, previamente gerada pelo
    módulo cartográfico;

-   Montar o conteúdo do relatório em **HTML técnico padronizado**;

-   Aplicar identidade visual institucional, incluindo:

    -   logotipos oficiais;

    -   cabeçalho e rodapé institucionais;

    -   tipografia padronizada;

-   Inserir automaticamente o **carimbo de status do alerta**,
    indicando:

    -   VIGENTE, ou

    -   ENCERRADO;

-   Converter o conteúdo HTML em documento PDF utilizando a biblioteca
    Dompdf;

-   Disponibilizar o relatório para visualização direta ou download pelo
    usuário autorizado.

**Estrutura do Relatório PDF**

O relatório técnico gerado segue uma estrutura fixa e padronizada,
composta por:

-   Identificação institucional do sistema e do órgão responsável;

-   Dados técnicos do alerta;

-   Descrição do tipo de evento e grau de severidade;

-   Riscos potenciais associados;

-   Recomendações operacionais;

-   Mapa geográfico real da área afetada;

-   Listagem de municípios afetados, organizados por Região de
    Integração;

-   Indicação explícita do status de vigência do alerta;

-   Data e hora de geração do documento.

**Entradas**

-   Identificador único do alerta;

-   Dados técnicos e territoriais persistidos no banco de dados;

-   Caminho da imagem do mapa do alerta armazenada no servidor;

-   Parâmetros institucionais de formatação do relatório.

**Saídas**

-   Documento PDF técnico, padronizado e institucionalmente válido;

-   Arquivo compatível com arquivamento digital, impressão e
    distribuição oficial.

**Dependências**

-   Biblioteca **Dompdf** para renderização do documento PDF;

-   Classe de persistência Database.php;

-   Serviço de processamento territorial para fornecimento dos dados
    consolidados;

-   Arquivos de identidade visual institucional armazenados no servidor;

-   Imagem do mapa do alerta gerada pelo módulo cartográfico.

**Decisão de Projeto**

A geração do relatório foi isolada em um serviço específico para
garantir:

-   Separação clara de responsabilidades entre interface, lógica de
    negócio e documentação;

-   Padronização visual e estrutural dos documentos emitidos;

-   Facilidade de manutenção e atualização do layout institucional;

-   Reutilização do serviço em diferentes módulos do sistema;

-   Conformidade com normas técnicas, exigências legais e processos de
    auditoria.

A utilização de um **mapa real renderizado a partir do ambiente
cartográfico do sistema** assegura fidelidade territorial, fortalecendo
a validade técnica e institucional do relatório como documento oficial
da Defesa Civil.

## **6. PROJETO DE INTERFACE**

O Projeto de Interface do Sistema Inteligente Multirriscos define a
organização visual, os elementos de interação e o comportamento das
telas do sistema, considerando o contexto operacional da Defesa Civil e
a necessidade de apoio à tomada de decisão em cenários críticos.

Este capítulo descreve os princípios de usabilidade adotados, a
estrutura geral da interface, as telas disponíveis, as ações possíveis e
os objetos de interface, garantindo coerência entre a camada visual, as
regras de negócio e o modelo de controle de acesso por perfis (RBAC).

### 6.1 Visão Geral da Interface

A interface do Sistema Inteligente Multirriscos é do tipo **Web
Responsiva**, desenvolvida para utilização em navegadores modernos, com
prioridade para ambientes desktop, sem prejuízo de uso em tablets e
dispositivos móveis.

O projeto da interface foi orientado pelos seguintes princípios:

-   Clareza e legibilidade das informações críticas;

-   Rapidez na identificação de situações de risco;

-   Redução de erros operacionais;

-   Padronização visual institucional;

-   Adequação das funcionalidades ao perfil do usuário autenticado;

-   Coerência entre interface, regras de negócio e controle de acesso.

A estrutura geral da interface é composta por:

-   **Cabeçalho (Header):** identificação institucional do sistema,
    identificação do usuário autenticado e opções de encerramento de
    sessão;

-   **Menu lateral (Sidebar):** navegação hierárquica entre os módulos
    do sistema;

-   **Área central de conteúdo:** exibição dinâmica das telas e
    funcionalidades;

-   **Rodapé:** informações institucionais e identificação da versão do
    sistema.

### 6.2 Telas do Sistema

**6.2.1 Tela de Autenticação (Login)**

**Objetivo:**\
Permitir o acesso seguro ao sistema por usuários previamente cadastrados
e autorizados.

**Elementos da interface:**

-   Campo para Email institucional;

-   Campo para senha;

-   Botão de autenticação;

-   Área de mensagens de feedback ao usuário.

**Regras de interação:**

-   Validação imediata das credenciais informadas;

-   Exibição de mensagens claras em caso de erro de autenticação;

-   Bloqueio de acesso para usuários com status inativo;

-   Redirecionamento automático para o Painel Operacional após
    autenticação bem-sucedida.

**6.2.2 Painel Operacional (Dashboard)**

**Objetivo:**\
Apresentar uma visão consolidada e estratégica da situação atual dos
alertas no território do Estado do Pará.

**Elementos da interface:**

-   Cartões de indicadores operacionais (KPIs);

-   Gráficos analíticos de severidade, tipologia e temporalidade;

-   Mapa interativo com os alertas ativos;

-   Painel lateral de inteligência territorial.

**Comportamento da interface:**

-   Atualização dinâmica conforme filtros aplicados;

-   Interação direta entre mapa, gráficos e painel lateral;

-   Destaque visual por grau de severidade;

-   Sincronização entre indicadores estatísticos e representação
    geoespacial.

**6.2.3 Tela de Listagem de Alertas**

**Objetivo:**\
Permitir a consulta estruturada, organizada e rastreável dos alertas
cadastrados no sistema.

**Elementos da interface:**

-   Tabela paginada de alertas;

-   Filtros por status, grau de severidade, período e fonte;

-   Indicadores visuais de situação do alerta;

-   Botões de ação contextual.

**Regras de interface (RBAC):**

-   Ações de edição, encerramento e cancelamento disponíveis apenas para
    perfis autorizados;

-   Usuários do perfil operacional possuem acesso apenas à visualização;

-   Ações não permitidas são apresentadas de forma desabilitada, com
    feedback explicativo.

**6.2.4 Tela de Detalhe do Alerta**

**Objetivo:**\
Exibir de forma completa e organizada todas as informações técnicas e
territoriais de um alerta específico.

**Elementos da interface:**

-   Dados técnicos do alerta;

-   Informações de severidade e vigência;

-   Relação de municípios e Regiões de Integração afetadas;

-   Imagem complementar associada ao alerta, quando existente;

-   Ações de exportação (PDF, CSV e KML).

**6.2.5 Tela de Cadastro e Edição de Alertas**

**Objetivo:**\
Permitir o registro e a manutenção de alertas multirriscos de forma
controlada e segura.

**Elementos da interface:**

-   Formulário técnico do alerta;

-   Mapa interativo para desenho ou edição do polígono geográfico;

-   Campo para upload opcional de imagem informativa;

-   Botões de salvamento e cancelamento.

**Regras de interface:**

-   Validação de campos obrigatórios antes do salvamento;

-   Bloqueio de edição do número oficial do alerta;

-   Reprocessamento territorial automático após alterações na área
    geográfica;

-   Feedback visual indicando sucesso ou falha da operação.

**6.2.6 Telas do Módulo de Análises**

**Objetivo:**\
Disponibilizar análises estratégicas e históricas para apoio à tomada de
decisão.

**Elementos da interface:**

-   Gráficos analíticos temporais;

-   Gráficos de severidade e tipologia;

-   Rankings territoriais;

-   Filtros dinâmicos dependentes (ano, região e município);

-   Cards explicativos dos índices analíticos.

**6.2.7 Telas de Gestão de Usuários**

**Objetivo:**\
Permitir a administração segura dos usuários do sistema.

**Elementos da interface:**

-   Listagem de usuários cadastrados;

-   Formulários de cadastro e edição;

-   Controle de perfil e status;

-   Ações administrativas.

**Regras de interface:**

-   Acesso exclusivo ao perfil ADMIN;

-   Proibição de auto alteração que resulte em perda de privilégios
    administrativos;

-   Feedback claro para ações restritas.

### 6.3 Ações e Objetos da Interface

As ações disponíveis na interface estão condicionadas ao perfil do
usuário, conforme o modelo RBAC.

**Principais ações:**

-   Autenticar usuário;

-   Cadastrar alerta;

-   Editar alerta;

-   Encerrar ou cancelar alerta;

-   Gerar relatório em PDF;

-   Exportar dados geográficos e tabulares;

-   Visualizar análises;

-   Gerenciar usuários.

**Objetos de interface:**

-   Botões contextuais;

-   Tabelas dinâmicas;

-   Mapas interativos;

-   Gráficos analíticos;

-   Modais informativos e de confirmação.

### 6.2 Princípios de Usabilidade e Segurança

O projeto de interface observa os seguintes princípios:

-   Prevenção de erros por meio de confirmações explícitas para ações
    críticas;

-   Feedback imediato ao usuário após cada ação;

-   Consistência visual e funcional em todas as telas;

-   Minimização de ações irreversíveis;

-   Clareza nas restrições de acesso por perfil.

A interface atua como **camada complementar de segurança**, reforçando o
controle de acesso implementado no backend e reduzindo a possibilidade
de erros operacionais em ambientes críticos.

## **7. MATRIZ DE REQUISITOS**

A Matriz de Requisitos estabelece a rastreabilidade entre as
necessidades do negócio, os requisitos funcionais e não funcionais do
sistema e os módulos responsáveis por sua implementação. Este artefato é
fundamental para controle de escopo, validação, auditoria e evolução do
Sistema Inteligente Multirriscos.

### 7.1 Requisitos Funcionais (RF)

  ------------------------------------------------------------------------------
  **Código**   **Requisito     **Descrição**                      **Módulo
               Funcional**                                        Associado**
  ------------ --------------- ---------------------------------- --------------
  RF-01        Autenticar      Permitir autenticação segura de    Usuários /
               usuário         usuários com base em credenciais   Segurança
                               válidas                            

  RF-02        Controlar       Restringir funcionalidades         Usuários /
               acesso por      conforme o perfil do usuário       Segurança
               perfil          (RBAC)                             

  RF-03        Importar alerta Permitir a importação automatizada Alertas
               do INMET        de alertas oficiais do INMET       

  RF-04        Cadastrar       Permitir o cadastro manual de      Alertas
               alerta manual   alertas multirriscos               

  RF-05        Editar alerta   Permitir a edição de dados e do    Alertas
                               polígono geográfico do alerta      

  RF-06        Encerrar alerta Permitir o encerramento controlado Alertas
                               de alertas vigentes                

  RF-07        Visualizar      Permitir consulta detalhada de     Alertas
               alertas         alertas cadastrados                

  RF-08        Gerar PDF       Gerar relatório oficial do alerta  Relatórios
               técnico         em formato PDF                     

  RF-09        Exportar dados  Permitir exportação de dados em    Relatórios
                               formatos CSV e KML                 

  RF-10        Visualizar mapa Exibir alertas ativos em mapa      Mapas
               multirriscos    interativo                         

  RF-11        Analisar        Disponibilizar análises temporais, Análises
               alertas         severidade e tipologia             

  RF-12        Gerenciar       Permitir cadastro, edição,         Usuários
               usuários        ativação e desativação de usuários 
  ------------------------------------------------------------------------------

### 7.2 Requisitos Não Funcionais (RNF)

  --------------------------------------------------------------------------
  **Código**   **Requisito Não     **Descrição**
               Funcional**         
  ------------ ------------------- -----------------------------------------
  RNF-01       Segurança           Garantir controle de acesso por perfil e
                                   proteção contra acessos não autorizados

  RNF-02       Desempenho          As consultas críticas devem responder em
                                   tempo adequado ao uso operacional

  RNF-03       Disponibilidade     O sistema deve estar disponível em
                                   ambiente de produção com alta
                                   confiabilidade

  RNF-04       Usabilidade         A interface deve ser intuitiva e
                                   orientada ao uso operacional

  RNF-05       Auditabilidade      Todas as ações críticas devem ser
                                   rastreáveis

  RNF-06       Escalabilidade      Permitir evolução funcional sem
                                   necessidade de reestruturação total

  RNF-07       Conformidade        Atender às normas legais, técnicas e
                                   institucionais aplicáveis
  --------------------------------------------------------------------------

### 7.3 Rastreabilidade dos Requisitos

  -----------------------------------------------------------------------
  **Requisito**            **Arquivos / Componentes Relacionados**
  ------------------------ ----------------------------------------------
  RF-01 / RF-02            Protect.php, usuarios/\*.php

  RF-03                    InmetService.php, importar_inmet.php

  RF-04 / RF-05            editar.php, salvar.php, atualizar.php

  RF-06                    encerrar_alerta.php

  RF-08                    PdfService.php, pdf.php

  RF-09                    listar.php, kml.php

  RF-10                    painel.php, mapas/inmet.php

  RF-11                    Analise\*Service.php, páginas de análises

  RF-12                    usuarios/\*.php
  -----------------------------------------------------------------------

## **8. APÊNDICE**

O Apêndice reúne informações técnicas complementares que subsidiam a
compreensão, validação, auditoria e evolução do Sistema Inteligente
Multirriscos. Este capítulo não introduz novos requisitos funcionais,
mas consolida artefatos de apoio à engenharia de software, garantindo
rastreabilidade técnica, transparência metodológica e conformidade
institucional.

**8.1 Diagramas UML**

Os diagramas UML apresentados neste apêndice têm como finalidade
representar graficamente a estrutura e o comportamento do sistema,
facilitando a compreensão da arquitetura e das interações entre seus
componentes.

**8.1.1 Diagrama de Casos de Uso**

O Diagrama de Casos de Uso representa as interações entre os perfis de
usuários e o sistema, evidenciando as funcionalidades disponíveis
conforme o modelo de controle de acesso baseado em papéis (RBAC).

Perfis contemplados:

-   Administrador (ADMIN)

-   Gestor (GESTOR)

-   Analista (ANALISTA)

-   Operações (OPERACOES)

Os principais casos de uso incluem:

-   Autenticar usuário;

-   Importar alerta do INMET;

-   Cadastrar alerta manual;

-   Editar alerta;

-   Encerrar ou cancelar alerta;

-   Visualizar alertas;

-   Gerar relatórios em PDF;

-   Exportar dados (CSV e KML);

-   Consultar análises;

-   Gerenciar usuários.

**8.1.2 Diagrama de Sequência**

O Diagrama de Sequência descreve o fluxo temporal das interações entre
usuário, interface, controladores, serviços e banco de dados.

Casos de sequência documentados:

-   Importação de alerta do INMET;

-   Salvamento de alerta com processamento territorial;

-   Edição de alerta com reprocessamento geoespacial;

-   Geração de relatório PDF.

Esses diagramas evidenciam a separação de responsabilidades e o fluxo
determinístico das operações críticas.

**8.1.3 Diagrama de Componentes**

O Diagrama de Componentes apresenta a organização lógica do sistema em
módulos e camadas, destacando:

-   Interface do usuário;

-   Controladores (Pages);

-   Serviços de negócio;

-   Núcleo de infraestrutura (Core);

-   Banco de dados.

Esse diagrama reforça a arquitetura em camadas e o baixo acoplamento
entre os componentes.

**8.2 Modelo de Dados Complementar**

**8.2.1 Diagrama Entidade-Relacionamento (DER)**

O Diagrama Entidade-Relacionamento ilustra visualmente as entidades do
banco de dados, seus atributos e relacionamentos, incluindo:

-   alertas;

-   alerta_municipios;

-   alerta_regioes;

-   municipios_regioes_pa;

-   usuarios.

O DER reforça a separação entre evento (alerta) e impacto territorial
(municípios e regiões), garantindo consistência analítica.

**8.2.2 Regras de Integridade de Dados**

As principais regras de integridade documentadas são:

-   Um alerta é registrado uma única vez;

-   Um alerta pode estar associado a múltiplos municípios;

-   Municípios pertencem a uma única Região de Integração;

-   Toda alteração na área geográfica de um alerta exige reprocessamento
    territorial completo;

-   Exclusão lógica preserva rastreabilidade histórica.

**8.3 Índices Analíticos e Fórmulas**

Este apêndice documenta os índices analíticos utilizados no sistema para
avaliação de pressão operacional.

**8.3.1 Índice de Pressão Territorial (IPT)**

O IPT representa a pressão acumulada sobre um município, considerando
severidade e duração dos alertas.

Fórmula conceitual:

IPT = Σ (alertas × peso da severidade × duração do alerta)

**8.3.2 Índice Regional de Pressão (IRP)**

O IRP avalia a pressão operacional em nível regional, considerando a
abrangência territorial dos alertas.

Fórmula conceitual:

IRP = Σ (alertas × peso da severidade × número de municípios afetados)

**8.3.3 Pesos de Severidade**

Os pesos adotados para os níveis de severidade são:

-   Baixo: 1

-   Moderado: 2

-   Alto: 3

-   Muito Alto: 4

-   Extremo: 5

Esses pesos são utilizados de forma padronizada em todas as análises do
sistema.

**8.4 Fluxos Operacionais Complementares**

**8.4.1 Fluxo de Importação de Alerta do INMET**

Etapas do fluxo:

1.  Inserção da URL do alerta pelo usuário;

2.  Leitura e parsing do conteúdo externo;

3.  Extração de metadados e polígono;

4.  Pré-visualização do alerta;

5.  Confirmação do salvamento;

6.  Processamento territorial automático;

7.  Persistência no banco de dados.

**8.4.2 Fluxo de Geração de Relatório PDF**

Etapas do fluxo:

1.  Solicitação do relatório pelo usuário autorizado;

2.  Recuperação dos dados do alerta;

3.  Recuperação das informações territoriais;

4.  Inserção do mapa real do alerta;

5.  Geração do documento PDF;

6.  Disponibilização para visualização ou download.

**8.5 Padrões Técnicos e Boas Práticas**

Este sistema adota os seguintes padrões e boas práticas:

-   Arquitetura em camadas;

-   Separação de responsabilidades;

-   Controle de acesso baseado em papéis (RBAC);

-   Uso de prepared statements no acesso ao banco de dados;

-   Processamento geoespacial offline e determinístico;

-   Padronização visual e documental de relatórios.

**8.6 Considerações para Evolução do Sistema**

O material apresentado neste apêndice fornece base técnica para:

-   Evolução do sistema para integração com APIs externas;

-   Implementação de notificações automáticas;

-   Expansão do módulo de análises;

-   Criação de API pública institucional;

-   Incorporação de técnicas de inteligência artificial.

**8.7 Encerramento do Documento**

O Apêndice consolida os principais artefatos técnicos de apoio ao
Sistema Inteligente Multirriscos, garantindo que o projeto possua
documentação suficiente para manutenção, auditoria, evolução e
reutilização institucional, atendendo plenamente aos princípios da
engenharia de software e às exigências de sistemas governamentais.

**9. AJUSTES EVOLUTIVOS E MANUTENÇÃO CONTROLADA DO SISTEMA**

**9.1 Finalidade do Capítulo**

Este capítulo tem como finalidade documentar os **ajustes evolutivos,
correções funcionais e melhorias técnicas** implementadas no Sistema
Inteligente Multirriscos ao longo de seu ciclo de vida, após a
implantação inicial em ambiente de produção.

Os ajustes aqui descritos seguem os princípios de **manutenção corretiva
e evolutiva**, conforme a norma **ISO/IEC 12207**, assegurando:

-   Continuidade operacional do sistema;

-   Preservação da arquitetura original;

-   Rastreabilidade técnica das alterações;

-   Redução de riscos de regressão;

-   Transparência para auditoria e evolução futura.

Todos os ajustes são realizados de forma **cirúrgica**, sem refatorações
destrutivas, respeitando integralmente os módulos, fluxos e
responsabilidades definidos neste documento.

**9.2 Diretrizes Gerais para Ajustes no Sistema**

Os ajustes no Sistema Inteligente Multirriscos obedecem às seguintes
diretrizes obrigatórias:

-   Análise prévia do impacto arquitetural;

-   Alterações localizadas, com escopo claramente delimitado;

-   Preservação dos contratos existentes entre camadas;

-   Manutenção do modelo de controle de acesso baseado em papéis (RBAC);

-   Garantia de integridade dos dados operacionais, territoriais e
    analíticos;

-   Compatibilidade com o ambiente de hospedagem (PHP + MySQL/MariaDB);

-   Validação funcional antes da liberação em produção.

Nenhum ajuste deve comprometer os fluxos críticos de:

-   Cadastro de alertas;

-   Processamento territorial;

-   Geração de relatórios oficiais;

-   Análises estatísticas e territoriais.

**9.3 Ajuste Evolutivo 01 -- Correção do Fluxo de Substituição de
Arquivo KML na Edição de Alertas**

**9.3.1 Contexto do Ajuste**

Durante a operação do sistema, foi identificado um comportamento
inconsistente no fluxo de **edição de alertas** envolvendo a
substituição de arquivos **KML**.

O sistema permitia corretamente:

-   Upload de KML no cadastro inicial do alerta (cadastrar.php /
    salvar.php);

Entretanto, ao **editar um alerta existente**, o envio de um novo
arquivo KML não era corretamente reconhecido, ocasionando a perda da
referência ao KML anterior ou a não substituição adequada do arquivo.

**9.3.2 Causa Raiz Identificada**

A causa do problema estava localizada no arquivo:

-   atualizar.php

O sistema inicializava indevidamente os campos relacionados à origem da
área geográfica do alerta durante a edição, independentemente do envio
de um novo KML.

Com isso, ocorria:

-   Sobrescrita do campo area_origem para DESENHO;

-   Anulação do campo kml_arquivo;

-   Perda da associação entre o alerta e o arquivo KML previamente
    cadastrado;

-   Quebra da regra de precedência entre KML e área desenhada no mapa.

**9.3.3 Regra Técnica Estabelecida**

Foi formalizada a seguinte regra de negócio e persistência:

**Os campos area_origem e kml_arquivo, somente devem ser alterados
quando um novo arquivo KML for explicitamente enviado pelo usuário
durante a edição do alerta.**

Caso contrário, os valores previamente armazenados no banco de dados
devem ser **preservados integralmente**.

Essa regra garante:

-   Consistência territorial;

-   Rastreabilidade do dado geoespacial;

-   Coerência entre mapa, banco de dados, análises e relatórios.

**9.3.4 Solução Implementada**

A solução adotada consistiu em:

1.  Recuperar, no início do processo de edição, os valores atuais de:

    -   area_origem

    -   kml_arquivo

    -   informacoes

2.  Remover a inicialização forçada desses campos durante a edição;

3.  Sobrescrever os valores **somente** quando um novo KML for enviado;

4.  Manter inalterados os dados quando a edição não envolver
    substituição do arquivo KML.

A correção foi implementada de forma **localizada**, sem impacto em
outros módulos do sistema.

**9.3.5 Arquivos Impactados**

  -----------------------------------------------------------------------
  **Arquivo**           **Tipo de Alteração**
  --------------------- -------------------------------------------------
  editar.php            Nenhuma alteração necessária

  atualizar.php         Correção lógica de persistência
  -----------------------------------------------------------------------

**9.3.6 Impactos Funcionais**

Após a aplicação do ajuste, o sistema passou a apresentar o seguinte
comportamento:

-   Edição de alerta sem envio de novo KML:

    -   Mantém o KML previamente cadastrado;

-   Edição de alerta com envio de novo KML:

    -   Substitui corretamente o arquivo anterior;

    -   Atualiza a área geográfica do alerta;

    -   Reprocessa automaticamente os municípios e regiões afetadas;

-   Nenhuma regressão identificada nos módulos de:

    -   Mapas;

    -   Relatórios PDF;

    -   Exportações geográficas;

    -   Análises estatísticas;

    -   Controle de acesso.

**9.3.7 Conformidade Arquitetural**

O ajuste está em plena conformidade com:

-   Arquitetura em camadas;

-   Padrão MVC adotado;

-   Modelo de dados documentado;

-   Princípios de governança de dados;

-   Processamento territorial determinístico;

-   Normas de engenharia de software aplicáveis.

**9.4 Registro de Manutenção**

Este ajuste caracteriza-se como:

-   **Tipo de manutenção:** Corretiva com impacto evolutivo

-   **Criticidade:** Média

-   **Risco operacional:** Eliminado após correção

-   **Necessidade de refatoração estrutural:** Não

-   **Compatibilidade retroativa:** Total

O registro deste ajuste assegura rastreabilidade técnica e fornece base
documental para futuras evoluções do Sistema Inteligente Multirriscos.

**9.5 Considerações Finais**

A formalização dos ajustes evolutivos neste documento garante que o
Sistema Inteligente Multirriscos permaneça:

-   Tecnologicamente consistente;

-   Auditável;

-   Seguro;

-   Evolutivo;

-   Alinhado às necessidades operacionais da Defesa Civil do Estado do
    Pará.

Novos ajustes deverão seguir o mesmo padrão técnico, documental e
metodológico aqui estabelecido.

**9.6 Ajuste Evolutivo 2 -- Implementação do Módulo de Histórico de
Ações do Usuário (Auditoria)**

**9.6.1 Contextualização**

Durante a evolução do Sistema Inteligente Multirriscos (SIMD),
identificou-se a necessidade de **rastreabilidade completa das ações
executadas pelos usuários**, visando atender princípios de **auditoria,
transparência, controle operacional e responsabilização**, especialmente
em um sistema crítico utilizado pela Defesa Civil.

Essa necessidade tornou-se ainda mais relevante considerando:

-   Operações multiusuário;

-   Ações sensíveis (criação, edição, encerramento e exportação de
    dados);

-   Geração de documentos oficiais (PDF, CSV, KML);

-   Conformidade com boas práticas de governança de TI.

**9.6.2 Objetivo da Tarefa**

Implementar um **módulo centralizado de histórico de ações do usuário**,
capaz de registrar de forma confiável, segura e padronizada todas as
operações relevantes realizadas no sistema, sem impactar o fluxo
principal da aplicação.

**9.6.3 Escopo da Implementação**

A tarefa contemplou:

-   Criação da tabela historico_usuarios;

-   Desenvolvimento do serviço central HistoricoService;

-   Padronização dos códigos de ação (acao_codigo);

-   Registro automático de ações críticas do sistema;

-   Implementação de mecanismo antifalha (fail-safe);

-   Prevenção de registros duplicados por requisição;

-   Inclusão de metadados de auditoria.

**9.6.4 Estrutura de Dados Implementada**

A tabela historico_usuarios foi estruturada para armazenar informações
completas de auditoria, conforme abaixo:

-   usuario_id -- Identificador do usuário;

-   usuario_nome -- Nome do usuário no momento da ação;

-   acao_codigo -- Código padronizado da ação;

-   acao_descricao -- Descrição textual da ação;

-   referencia -- Informação contextual da ação (ex.: número do alerta);

-   hash_acao -- Hash criptográfico da ação (auditoria);

-   ip_usuario -- Endereço IP do usuário;

-   user_agent -- Navegador/dispositivo utilizado;

-   data_hora -- Data e hora do evento.

**9.6.5 Serviço Central de Registro -- HistoricoService**

Foi criado o serviço HistoricoService, responsável por centralizar todos
os registros de histórico, garantindo:

-   **Isolamento da lógica de auditoria**;

-   **Reutilização em múltiplos módulos**;

-   **Consistência dos dados registrados**;

-   **Baixo acoplamento com regras de negócio**.

**Características técnicas relevantes:**

-   Registro encapsulado em bloco try/catch;

-   Falha silenciosa proposital (não interrompe a operação principal);

-   Geração automática de hash criptográfico (SHA-256);

-   Proteção contra múltiplos registros na mesma requisição HTTP.

**9.6.6 Ações Monitoradas**

Foram padronizados códigos de ação para auditoria, incluindo, mas não se
limitando a:

-   CADASTRAR_ALERTA

-   EDIT_ALERTA

-   ENC_ALERTA

-   CAN_ALERTA

-   IMPORTAR_INMET

-   BAIXAR_PDF

-   BAIXAR_KML

-   BAIXAR_CSV

Essa padronização possibilita:

-   Filtros eficientes;

-   Relatórios consolidados;

-   Análises estatísticas futuras.

**9.6.7 Benefícios Obtidos**

-   Rastreabilidade completa das ações;

-   Base sólida para auditoria e compliance;

-   Melhoria da governança do sistema;

-   Suporte a relatórios gerenciais;

-   Preparação para controle de incidentes e responsabilização.

**9.6.8 Classificação da Manutenção**

**Manutenção Evolutiva**, conforme definido pela ISO/IEC 14764, pois
adiciona novas funcionalidades sem alterar comportamento existente.

**9.7 Ajuste Evolutivo 03 -- Implementação do Relatório de Histórico de
Usuários em PDF**

**9.7.1 Contextualização**

Com a consolidação do módulo de histórico, surgiu a necessidade de
**transformar os registros de auditoria em informação gerencial**,
permitindo:

-   Análise de uso do sistema;

-   Auditoria formal de ações;

-   Geração de documentos oficiais;

-   Compartilhamento institucional de informações.

Para isso, foi definido o desenvolvimento de um **Relatório de Histórico
de Usuários em formato PDF**, seguindo o padrão visual e institucional
já utilizado nos alertas do sistema.

**9.7.2 Objetivo da Tarefa**

Desenvolver um relatório em PDF que consolide as ações dos usuários, com
base em filtros aplicados, garantindo:

-   Padronização visual institucional;

-   Integridade das informações;

-   Possibilidade de auditoria documental;

-   Facilidade de leitura e arquivamento.

**9.7.3 Escopo da Implementação**

A tarefa incluiu:

-   Criação do serviço PdfHistoricoService;

-   Integração com a biblioteca Dompdf;

-   Reutilização do cabeçalho e rodapé institucional;

-   Implementação de filtros dinâmicos;

-   Inclusão de totalizadores por tipo de ação;

-   Implementação de hash de verificação do relatório;

-   Paginação automática do documento;

-   Layout em orientação paisagem.

**9.7.4 Estrutura do Relatório PDF**

O relatório foi estruturado contendo:

**Cabeçalho Institucional**

-   Logos oficiais;

-   Identificação do sistema;

-   Título: **"Relatório dos Usuários"**.

**Descrição dos Filtros Aplicados**

-   Período selecionado ou "Todos";

-   Usuário específico ou "Todos";

-   Tipo de ação ou "Todas";

-   Nome do usuário que gerou o relatório.

**Totalizadores por Tipo de Ação**

-   Quantidade de registros por acao_codigo;

-   Visão consolidada para análise gerencial.

**Tabela de Registros**

-   Data/Hora;

-   Usuário;

-   Código da ação;

-   Descrição da ação;

-   Referência contextual.

**Rodapé Institucional**

-   Logos das instituições parceiras;

-   Identificação da subdivisão responsável (SIMD);

-   Hash de verificação do documento.

**9.7.5 Integridade e Auditoria do Documento**

Cada relatório gerado possui um **hash criptográfico (SHA-256)**
calculado a partir de:

-   Conteúdo dos registros;

-   Filtros aplicados;

-   Data de geração.

Esse mecanismo garante:

-   Integridade do documento;

-   Não repúdio;

-   Validade para fins administrativos.

**9.7.6 Tratamento de Quebra de Página**

Foram aplicadas regras específicas para:

-   Repetição automática do cabeçalho da tabela;

-   Evitar quebra de linha dentro de registros;

-   Garantir legibilidade em relatórios extensos.

**9.7.7 Decisão Técnica sobre Registro no Histórico**

Durante a implementação, identificou-se que o registro da ação **"Baixar
Relatório PDF"** poderia gerar múltiplos eventos por limitações do ciclo
de renderização do Dompdf.

Como decisão técnica e de estabilidade, optou-se por:

-   **Não registrar a geração do relatório PDF no histórico**;

-   Evitar poluição da base de auditoria;

-   Garantir consistência e confiabilidade dos dados históricos.

**9.7.8 Benefícios Obtidos**

-   Visão gerencial consolidada;

-   Documento oficial padronizado;

-   Suporte à auditoria institucional;

-   Base para análises estratégicas;

-   Preparação para expansão futura (CSV, BI, dashboards).

**9.7.9 Classificação da Manutenção**

**Manutenção Evolutiva**, pois adiciona novas funcionalidades analíticas
e documentais sem impactar regras existentes.

**9.8 Considerações Finais**

As Tarefas 2 e 3 consolidam o SIMD como um sistema:

-   Auditável;

-   Governável;

-   Alinhado às boas práticas de Engenharia de Software;

-   Preparado para uso institucional e fiscalização.

Essas evoluções fortalecem a confiabilidade operacional e ampliam o
valor estratégico do sistema no contexto da Defesa Civil do Estado do
Pará.

**9.9 Ajuste Evolutivo 4 -- Correção na Importação de Arquivos KML com
Múltiplos Polígonos no Cadastro de Alertas**

**9.9.1 Contextualização do Problema**

Durante o uso do sistema, foi identificado um comportamento incorreto no
**cadastro de novos alertas** quando o usuário realizava o upload de um
arquivo **KML contendo múltiplas áreas geográficas distintas**
(múltiplos polígonos).

Nessa situação, ao salvar o alerta diretamente pelo arquivo
cadastrar.php, o sistema interpretava de forma inadequada as coordenadas
do KML, ocasionando a **interligação indevida dos vértices dos
polígonos**, formando uma única geometria contínua e incorreta no mapa.

Como consequência operacional, os pontos das áreas afetadas apareciam
conectados entre si, descaracterizando a representação espacial real do
território afetado.

**9.9.2 Sintoma Operacional Identificado**

-   Arquivos KML com **múltiplos polígonos independentes** resultavam
    em:

    -   Polígonos conectados incorretamente;

    -   Linhas artificiais ligando áreas geográficas distintas;

    -   Representação cartográfica inconsistente no mapa e nos
        relatórios.

-   O problema **não ocorria** quando o mesmo KML era reenviado no fluxo
    de edição (editar.php), evidenciando uma inconsistência entre os
    fluxos de cadastro e edição.

**9.9.3 Análise da Causa Raiz**

A análise técnica demonstrou que o erro estava localizado exclusivamente
no fluxo de **cadastro inicial do alerta** (salvar.php), onde:

-   O processamento do KML utilizava uma lógica simplificada de leitura
    manual das coordenadas (simplexml + xpath), concatenando todos os
    pontos encontrados em um único array;

-   Essa abordagem ignorava a estrutura nativa do KML para **Polygon** e
    **MultiPolygon**, tratando múltiplas áreas como se fossem uma única
    geometria;

-   Já no fluxo de edição, o sistema utilizava corretamente o método
    centralizado TerritorioService::kmlParaGeojson(), capaz de
    interpretar corretamente geometrias complexas.

Portanto, havia uma **quebra de padrão arquitetural** entre os dois
fluxos.

**9.9.4 Estratégia de Correção Adotada**

Para corrigir o problema de forma definitiva e alinhada às boas práticas
de engenharia de software, foi adotada a seguinte estratégia:

-   **Unificação do processamento de KML**:

    -   O fluxo de cadastro (salvar.php) passou a utilizar o mesmo
        método robusto já empregado no fluxo de edição.

-   Centralização da conversão KML → GeoJSON no serviço:

    -   TerritorioService::kmlParaGeojson()

Essa abordagem elimina duplicação de lógica, reduz risco de regressão e
garante consistência sistêmica.

**9.9.5 Implementação Técnica da Correção**

O trecho responsável pela conversão manual do KML foi removido e
substituído pelo processamento padronizado, conforme abaixo:

/\* ===== KML → GEOJSON (PADRÃO DO SISTEMA) ===== \*/

\$kmlConteudo = file_get_contents(\$destino);

\$geojsonConvertido = TerritorioService::kmlParaGeojson(\$kmlConteudo);

if (!\$geojsonConvertido) {

die(\'Arquivo KML inválido ou sem geometria suportada.\');

}

\$area_geojson = json_encode(\$geojsonConvertido);

\$area_origem = \'KML\';

Com isso, o sistema passou a:

-   Reconhecer corretamente **Polygon** e **MultiPolygon**;

-   Preservar a independência espacial entre áreas distintas;

-   Garantir a integridade do GeoJSON armazenado no banco de dados.

**9.9.6 Resultados Obtidos Após o Ajuste**

Após a implementação da correção, foram observados os seguintes
resultados:

-   Polígonos múltiplos passam a ser exibidos corretamente no mapa, sem
    interligações indevidas;

-   O comportamento do cadastro passou a ser idêntico ao da edição de
    alertas;

-   Eliminação da necessidade de retrabalho operacional (editar alerta
    apenas para corrigir geometria);

-   Maior confiabilidade na geração de mapas e relatórios PDF;

-   Padronização do processamento geoespacial em todo o sistema.

**9.9.7 Impacto na Manutenção e Evolução do Sistema**

Este ajuste contribui diretamente para:

-   A **manutenção controlada** do sistema, reduzindo código duplicado;

-   A **robustez da inteligência territorial**, especialmente em
    cenários de múltiplas áreas afetadas;

-   A **escalabilidade futura**, permitindo suporte seguro a arquivos
    KML mais complexos;

-   O fortalecimento da governança técnica do módulo de alertas.

**9.9.8 Status do Ajuste**

-   **Status**: Implementado e validado

-   **Tipo**: Ajuste evolutivo corretivo

-   **Risco de regressão**: Baixo

-   **Dependências**: TerritorioService::kmlParaGeojson()

-   **Ambiente validado**: Cadastro, visualização, edição e geração de
    relatórios

**9.10 Ajustes evolutivos 05 - Evolutivos e Manutenção Controlada do
Sistema**

**9.10.1 Contextualização**

Durante a análise dos mecanismos de controle de acesso do *Sistema
Inteligente Multirriscos*, identificou-se que o menu **"Histórico do
Usuário"** estava sendo exibido de forma indiscriminada para todos os
perfis autenticados no sistema.

Essa condição contrariava os princípios de **segurança da informação**,
**segregação de funções** e **boas práticas de governança**, uma vez que
o histórico de ações contém informações sensíveis relacionadas à
auditoria operacional e administrativa do sistema.

**9.10.2 Problema Identificado**

O arquivo responsável pela renderização do menu lateral (\_sidebar.php)
não realizava validação de perfil antes de exibir o item:

-   🕓 **Histórico do Usuário**

Como consequência:

-   Perfis não autorizados (ex.: ANALISTA, OPERACOES) visualizavam um
    recurso que não deveriam acessar;

-   O controle de acesso estava restrito apenas à camada de backend, sem
    reforço na camada de interface;

-   Havia risco de exposição indevida de informações administrativas.

**9.10.3 Objetivo do Ajuste**

Restringir a visualização do menu **Histórico do Usuário**
exclusivamente aos perfis:

-   **ADMIN**

-   **GESTOR**

Garantindo que:

-   O menu não seja exibido para perfis não autorizados;

-   O controle de acesso seja aplicado também na camada de interface;

-   O sistema mantenha coerência entre permissões funcionais e visuais.

**9.10.4 Solução Implementada**

Foi realizado um ajuste cirúrgico no arquivo \_sidebar.php, envolvendo a
inclusão de uma validação explícita de perfil antes da renderização do
item de menu.

A lógica adotada utiliza a função in_array(), já empregada em outras
partes do sistema, garantindo padronização e fácil manutenção.

**9.10.5 Trecho de Código Ajustado**

\<?php if (in_array(\$usuario\[\'perfil\'\], \[\'ADMIN\',
\'GESTOR\'\])): ?\>

\<a href=\"/pages/historico/index.php\"\>🕓 Histórico do Usuário\</a\>

\<?php endif; ?\>

Esse ajuste garante que o menu seja exibido **somente** quando o usuário
autenticado possuir um dos perfis autorizados.

**9.10.6 Resultados Obtidos**

Após a implementação do ajuste, foram observados os seguintes
resultados:

-   ✅ Usuários com perfil **ADMIN** visualizam o menu Histórico do
    Usuário;

-   ✅ Usuários com perfil **GESTOR** visualizam o menu Histórico do
    Usuário;

-   ❌ Usuários com perfis **ANALISTA** e **OPERACOES** não visualizam o
    menu;

-   ✅ O menu **Usuários** permanece exclusivo para o perfil **ADMIN**;

-   ✅ Nenhum impacto negativo foi identificado nos demais módulos do
    sistema.

**9.10.7 Boas Práticas Atendidas**

O ajuste realizado atende às seguintes boas práticas de engenharia de
software e segurança da informação:

-   Aplicação do princípio do **menor privilégio**;

-   Reforço do controle de acesso na **camada de apresentação**;

-   Coerência entre permissões de backend e frontend;

-   Código simples, legível e de fácil manutenção;

-   Redução do risco de exposição indevida de informações sensíveis.

**9.10.8 Considerações Finais**

A Tarefa 5 consolida o processo de **endurecimento progressivo da
segurança do sistema**, alinhando-se às ações anteriores de manutenção
controlada.

Esse ajuste contribui diretamente para a confiabilidade operacional,
governança do sistema e aderência a práticas recomendadas para sistemas
críticos de apoio à Defesa Civil.

**9.11 Ajuste Evolutivo 06 -- Correção de Fuso Horário no Histórico do
Usuário**

**Descrição do Problema**

Foi identificado que, no módulo **Histórico do Usuário**, o horário das
ações registradas estava sendo exibido com **diferença de três horas em
relação ao horário local** do Estado do Pará. As ações apareciam
adiantadas, o que comprometia a confiabilidade das informações para fins
de auditoria, rastreabilidade e análise operacional.

A causa do problema estava relacionada ao **armazenamento das datas em
UTC no banco de dados**, combinado com uma **conversão automática
adicional no frontend (JavaScript)**, resultando em uma dupla conversão
de fuso horário.

**Análise Técnica**

-   O campo data_hora da tabela historico_usuarios é corretamente
    armazenado em **UTC**, seguindo boas práticas de sistemas
    distribuídos.

-   Na listagem (index.php), o horário era convertido implicitamente
    pelo PHP sem ajuste explícito de fuso.

-   No modal de detalhes, o JavaScript aplicava novamente a conversão
    para o fuso local do navegador, gerando um acréscimo indevido de +3
    horas.

-   Essa duplicidade de conversão causava inconsistência visual entre
    banco, listagem e relatórios.

**Solução Implementada**

A correção foi realizada de forma **centralizada, segura e aderente às
boas práticas de engenharia de software**, conforme descrito abaixo:

1.  **Padronização do armazenamento**

    -   Mantido o armazenamento do campo data_hora em **UTC** no banco
        de dados, sem alterações estruturais.

2.  **Conversão explícita no backend**

    -   A conversão de fuso horário passou a ser feita diretamente na
        **query SQL**, utilizando CONVERT_TZ, transformando UTC para o
        fuso local **America/Belem (-03:00)**.

    -   Criado o alias data_hora_local para uso exclusivo na camada de
        apresentação.

3.  **Exibição unificada**

    -   A tabela de listagem e o modal de detalhes passaram a utilizar
        exclusivamente o campo data_hora_local.

    -   Removida qualquer conversão de data/hora via JavaScript,
        evitando interpretações automáticas do navegador.

4.  **Consistência entre módulos**

    -   A mesma lógica de horário local passou a ser utilizada em:

        -   Listagem do histórico

        -   Modal de detalhes

        -   Relatórios em PDF

        -   Auditorias e exportações futuras

**Resultado Obtido**

-   O horário das ações passou a ser exibido corretamente no **horário
    local do Estado do Pará**.

-   Eliminada a inconsistência de +3 horas entre banco, interface e
    relatórios.

-   Garantida maior confiabilidade dos dados para fins de:

    -   Auditoria

    -   Controle operacional

    -   Análise histórica de eventos

-   Solução alinhada às boas práticas de sistemas críticos e
    governamentais.

**Impacto no Sistema**

-   ✔ Nenhuma alteração estrutural no banco de dados

-   ✔ Nenhum impacto negativo em outros módulos

-   ✔ Melhoria direta na integridade e confiabilidade das informações

-   ✔ Código mais limpo, previsível e auditável

**Classificação da Intervenção**

-   **Tipo:** Ajuste Evolutivo / Correção Funcional

-   **Risco:** Baixo

-   **Complexidade:** Média

-   **Status:** Implementado e validado

**9.12 Ajustes Evolutivos -- 07**

Este item descreve os **ajustes evolutivos realizados na Versão 07 do
Sistema Inteligente Multirriscos**, decorrentes da fase de testes
funcionais, validações operacionais e integração entre os módulos de
**Cadastro, Edição, Visualização Geográfica e Geração de PDF de
Alertas**.

Os ajustes tiveram como objetivo **corrigir inconsistências, ampliar
compatibilidade de formatos geoespaciais, alinhar frontend e backend, e
garantir estabilidade na geração de produtos finais (PDF)**.

**9.12.1 Ajustes no Módulo de Cadastro de Alertas**

**a) Integração robusta de arquivos KML**

-   Implementado suporte ampliado para **diferentes estruturas de
    arquivos KML**, incluindo:

    -   Polígonos simples

    -   MultiPolygon

    -   GeometryCollection

    -   KMLs provenientes de SIGs diversos (QGIS, Google Earth, buffers
        e camadas temáticas)

-   A leitura do KML passou a ser feita via **Leaflet Omnivore**,
    garantindo maior compatibilidade do que métodos baseados apenas em
    parsing XML.

**b) Prioridade do KML sobre o desenho manual**

-   Quando um arquivo KML é carregado:

    -   O desenho manual no mapa é automaticamente desabilitado

    -   O KML assume **prioridade absoluta** como fonte da área afetada

    -   O campo oculto area_geojson é preenchido exclusivamente com o
        GeoJSON derivado do KML

**c) Normalização do GeoJSON**

-   Implementado processo de **normalização das geometrias**, garantindo
    que apenas:

    -   Polygon

    -   MultiPolygon\
        sejam persistidos no banco de dados.

-   Geometrias incompatíveis são descartadas de forma controlada.

**9.12.2 Ajustes no Módulo de Edição de Alertas**

**a) Alinhamento com o fluxo de cadastro**

-   O módulo de edição passou a utilizar **a mesma lógica do cadastro**,
    garantindo:

    -   Consistência entre criação e atualização

    -   Reutilização do padrão GeoJSON/KML

    -   Compatibilidade total com o backend (atualizar.php)

**b) Controle de origem da área geográfica**

-   Introduzido o conceito de **origem da área**:

    -   DESENHO

    -   KML

-   Caso a origem seja KML:

    -   O desenho manual permanece bloqueado

    -   Apenas novo upload de KML pode substituir a geometria existente

**c) Carregamento automático da geometria existente**

-   Ao abrir a edição:

    -   A área afetada previamente salva é renderizada automaticamente
        no mapa

    -   O mapa ajusta o zoom aos limites da geometria

    -   O campo area_geojson é sincronizado com o conteúdo exibido

**9.12.3 Ajustes no Backend -- Salvamento e Atualização**

**a) Validação geográfica aprimorada**

-   A validação da área deixou de verificar apenas o primeiro elemento
    do GeoJSON.

-   Passou a validar:

    -   Existência de features

    -   Existência de geometry

    -   Existência de coordinates

-   Isso garantiu compatibilidade com GeoJSONs compostos e oriundos de
    conversões KML.

**b) Conversão KML → GeoJSON centralizada**

-   A conversão de KML passou a ser tratada exclusivamente pelo serviço:

    -   TerritorioService::kmlParaGeojson()

-   Isso padronizou a estrutura geográfica utilizada em:

    -   Cadastro

    -   Edição

    -   Inteligência territorial

    -   Geração de mapas

**9.12.4 Ajustes no Módulo de Detalhe do Alerta**

**a) Geração automática da imagem do mapa**

-   Implementado mecanismo de **geração automática da imagem do mapa**
    via:

    -   Leaflet

    -   Leaflet-image

-   A imagem é gerada **somente quando ainda não existir**, evitando
    processamento desnecessário.

**b) Controle de estado da imagem**

-   Introduzida lógica de controle:

    -   imagem_pendente

    -   imagem_mapa

-   Enquanto a imagem não estiver pronta:

    -   O botão de geração do PDF permanece desabilitado

    -   Um indicador visual informa o processamento em andamento

**9.12.5 Ajustes no Módulo de Geração de PDF**

**a) Correção do problema de "tela preta"**

Durante os testes foi identificado que o PDF era gerado corretamente no
backend, porém **exibia tela preta no navegador**.

**Causa identificada:**

-   Cache agressivo do navegador (principalmente Chrome)

-   Reutilização de imagens PNG antigas ou inválidas

-   Cache de imagens Base64 embutidas no DOMPDF

**Solução aplicada:**

-   Implementação de controle de cache no fluxo de geração

-   Uso de parâmetros dinâmicos (cache-busting) nas URLs

-   Forçamento de regeneração correta das imagens do mapa

**b) Robustez na incorporação de imagens**

-   Implementada imagem de fallback para mapa indisponível

-   Validação da existência física da imagem antes da conversão Base64

-   Garantia de que o DOMPDF sempre receba uma imagem válida

**9.12.6 Ajustes no PdfService**

**a) Configuração avançada do DOMPDF**

-   Ativados os seguintes parâmetros:

    -   isRemoteEnabled

    -   isHtml5ParserEnabled

    -   Fonte padrão DejaVu Sans

-   Garantiu compatibilidade com:

    -   Caracteres especiais

    -   Acentuação

    -   Layout complexo

    -   Imagens Base64

**b) Organização do layout institucional**

-   Padronização do cabeçalho e rodapé

-   Inclusão de logotipos institucionais

-   Numeração automática de páginas

-   Carimbo automático de status do alerta:

    -   **VIGENTE**

    -   **ENCERRADO**

**9.12.7 Ajustes de Estabilidade e Experiência do Usuário**

-   Redução de erros silenciosos

-   Mensagens mais claras ao usuário

-   Prevenção de estados inconsistentes

-   Melhoria da confiabilidade do sistema em ambiente real de operação

**9.12.8 Resultado dos Ajustes Evolutivos**

Após a aplicação dos ajustes da **Versão 07**, o sistema passou a
apresentar:

-   ✔ Cadastro e edição estáveis de alertas

-   ✔ Compatibilidade ampliada com arquivos KML

-   ✔ Integração consistente entre mapa, banco de dados e PDF

-   ✔ Geração confiável de documentos oficiais

-   ✔ Maior robustez para uso institucional e operacional

**9.13 -- Regra de Comunicação do Alerta (Envio para COMPDEC)**

**9.13.1 Objetivo**

Estabelecer as regras, condições e fluxos para o **envio manual de
alertas por e-mail às Defesas Civis Municipais (COMPDEC)**, garantindo:

-   Confiabilidade da informação enviada

-   Evitar disparos indevidos ou duplicados

-   Rastreabilidade completa da ação

-   Conformidade com perfis de acesso

-   Coerência entre dados do alerta, mapa e PDF

**9.13.2 Princípios da Comunicação**

A comunicação de alertas segue os seguintes princípios:

-   **Envio consciente (decisão humana)**: o sistema **não envia alertas
    automaticamente**.

-   **Envio único por versão do alerta**: após envio, o alerta só pode
    ser reenviado mediante **edição e novo salvamento**.

-   **Envio restrito aos municípios afetados**: somente COMPDEC
    vinculadas aos municípios associados ao alerta recebem o e-mail.

-   **Envio condicionado à integridade do alerta**: o alerta precisa
    estar completo, validado e com mapa gerado.

**9.13.3 Perfis Autorizados**

  -----------------------------------------------------------------------
  **Perfil de Usuário**        **Permissão**
  ---------------------------- ------------------------------------------
  **ADMIN**                    Pode enviar alertas

  **GESTOR**                   Pode enviar alertas

  **ANALISTA**                 Pode enviar alertas

  **OPERACOES**                ❌ Não pode enviar alertas
  -----------------------------------------------------------------------

Usuários com perfil **OPERACOES** visualizam o botão de envio
**desabilitado**, com modal explicativo ao tentar interação.

**9.13.4 Estados do Alerta na Comunicação**

O campo **Comunicação**, na listagem de alertas, apresenta estados
distintos conforme as condições do alerta:

**a) Alerta não ativo**

-   Condição: status ≠ ATIVO

-   Comportamento: envio bloqueado

-   Exibição:\
    **Indisponível**

**b) Alerta ativo, porém sem mapa gerado**

-   Condição: status = ATIVO **e** imagem_mapa IS NULL

-   Comportamento: envio bloqueado

-   Motivo: o PDF seria gerado sem o mapa do alerta

-   Exibição:\
    **🗺️ Mapa não gerado**

-   Orientação ao usuário:

Acesse o detalhe do alerta para gerar o mapa antes de enviar.

**c) Alerta já enviado**

-   Condição: alerta_enviado_compdec = 1

-   Comportamento: envio bloqueado

-   Exibição:\
    **✅ Enviado**\
    com data e hora do envio (ajuste de fuso apenas para exibição)

-   Regra de reenvio:

Para reenviar o alerta, o usuário deve **editar e salvar** o alerta
novamente.

**d) Alerta apto para envio**

-   Condições cumulativas:

    -   status = ATIVO

    -   imagem_mapa existente

    -   alerta_enviado_compdec = 0

-   Exibição:

    -   Para ADMIN / GESTOR / ANALISTA:\
        **📧 Enviar alerta**

    -   Para OPERACOES:\
        Botão desabilitado + modal explicativo

**9.13.5 Regras de Envio do E-mail**

O envio do alerta por e-mail segue obrigatoriamente as regras abaixo:

1.  O alerta deve estar **ATIVO**

2.  O alerta deve possuir **mapa gerado**

3.  O alerta deve possuir **municípios vinculados**

4.  O envio ocorre **somente** para COMPDEC que:

    -   Possuem tem_compdec = SIM

    -   Possuem e-mail válido

    -   Pertencem aos municípios afetados pelo alerta

5.  O e-mail contém:

    -   Número do alerta

    -   Evento

    -   Gravidade

    -   Fonte

    -   Data do alerta

    -   Início e fim da vigência

    -   **PDF do alerta em anexo**, contendo o mapa e demais informações
        oficiais

**9.13.6 Controle de Duplicidade**

Para evitar múltiplos envios indevidos:

-   O botão **📧 Enviar alerta** é:

    -   Bloqueado imediatamente no clique

    -   Substituído visualmente por **"⏳ Enviando..."**

-   Após sucesso:

    -   O botão é substituído por **tag de status "Enviado"**

-   Em caso de erro:

    -   O botão é reativado automaticamente

**9.13.7 Registro em Banco de Dados**

Após o envio bem-sucedido, o sistema registra:

-   alerta_enviado_compdec = 1

-   data_envio_compdec = NOW() (UTC no banco)

O ajuste de fuso horário é aplicado **apenas na exibição**, não
alterando o padrão UTC do banco de dados.

**9.13.8 Registro em Histórico (Auditoria)**

Toda ação de envio é registrada no histórico do sistema com:

-   Usuário (ID e nome)

-   Tipo de ação: ENVIAR_ALERTA

-   Descrição: *Enviou alerta para COMPDEC*

-   Detalhamento:

    -   Número do alerta

    -   Quantidade de e-mails enviados

Esse registro garante **rastreabilidade completa**, atendendo requisitos
de auditoria institucional.

**9.13.9 Regra de Reenvio**

O reenvio de um alerta **não é permitido diretamente**.

Para liberar um novo envio, o usuário deve:

1.  Acessar **Editar Alerta**

2.  Salvar o alerta novamente\
    (o sistema invalida o envio anterior)

3.  Gerar novamente o mapa (se necessário)

4.  O botão **📧 Enviar alerta** volta a ficar disponível

**9.13.10 Considerações Finais**

A regra de Comunicação do Alerta foi projetada para:

-   Evitar falhas humanas

-   Garantir consistência entre alerta, mapa e PDF

-   Proteger a credibilidade institucional da Defesa Civil

-   Assegurar governança, controle e rastreabilidade

**9.14 Ajuste Evolutivo 07 -- Implementação do Módulo de Relatório
Analítico Multirriscos**

**9.14.1 Contextualização**

Durante a evolução do Sistema Inteligente Multirriscos, identificou-se a
necessidade de disponibilizar uma visão analítica consolidada dos
alertas, permitindo a extração de indicadores estratégicos de forma
dinâmica, com base em filtros temporais e territoriais.

Até então, o sistema apresentava análises apenas em nível visual
(dashboard), sem a possibilidade de:

-   consolidação estruturada das informações

-   visualização em formato modal técnico

-   geração de documento oficial analítico em PDF

A ausência desse recurso limitava o uso institucional das análises para:

-   apoio à tomada de decisão

-   compartilhamento formal

-   arquivamento técnico

-   produção de inteligência operacional

**9.14.2 Objetivo da Implementação**

Desenvolver o **Relatório Analítico Multirriscos**, com:

-   filtros dinâmicos

-   visualização em modal

-   geração de PDF institucional

mantendo total aderência à arquitetura do sistema.

**9.14.3 Escopo da Implementação**

A tarefa contemplou:

**Frontend**

-   Criação dos filtros dinâmicos:

    -   Ano

    -   Mês

    -   Região de Integração

    -   Município

-   Dependência hierárquica entre Região → Município

-   Modal de visualização analítica

-   Botão de geração de PDF com envio dos parâmetros via query string

**Backend**

-   Criação da API:

/pages/analises/api/analise_global.php

-   Consolidação das análises no serviço:

AnaliseGlobalService

-   Integração dos serviços:

-   Severidade

-   Municípios impactados

-   Alertas por evento

-   Análise temporal

-   Tipologia

-   Índices de risco (IRP e IPT)

**Geração do PDF**

-   Criação do serviço:

RelatorioAnaliticoPdfService

com:

-   layout institucional

-   capa técnica

-   sumário

-   seções analíticas

-   cabeçalho e rodapé padronizados

-   paginação automática

**9.14.4 Estrutura Analítica do Relatório**

O relatório passou a conter:

**Parâmetros do relatório**

-   Ano

-   Mês (convertido para nome)

-   Região

-   Município

-   Data e hora de geração (fuso local)

**Seção -- Severidade**

-   Distribuição por severidade

-   Municípios mais impactados

-   Quantidade de alertas por evento

-   Duração média por evento

**Seção -- Temporal**

-   Evolução anual

-   Sazonalidade mensal

-   Comparativo mensal por tipo de evento

-   Frequência por período do dia

**Seção -- Tipologia**

-   Correlação evento × severidade

-   Tipologia por Região de Integração

**Seção -- Índices de Risco**

-   IRP

-   IPT

**9.14.5 Padronização com o Modal Analítico**

O PDF passou a reproduzir exatamente a mesma estrutura do modal
analítico, garantindo:

-   coerência visual

-   consistência dos dados

-   rastreabilidade entre interface e documento

**9.14.6 Decisões Técnicas Relevantes**

-   Limitação controlada de registros nas tabelas para reduzir consumo
    de memória

-   Conversão de imagens institucionais para Base64 apenas quando
    necessário

-   Ajuste de fuso horário exclusivamente na camada de apresentação

-   Separação total entre:

    -   PDF analítico

    -   PDF de alerta

Preservando os contratos existentes do sistema.

Documento De Especificação De P...

**9.14.7 Identidade Visual Institucional**

Implementado:

-   Capa institucional exclusiva

-   Cabeçalho técnico padronizado

-   Rodapé com logotipos dos órgãos parceiros

-   Numeração automática de páginas

-   Nome do arquivo com data e hora de geração

**9.14.8 Estabilidade e Performance**

Foram aplicadas medidas para:

-   evitar estouro de memória do Dompdf

-   impedir quebras de tabela entre páginas

-   manter subtítulos vinculados às tabelas

-   garantir renderização em ambiente de produção

**9.14.9 Impactos Funcionais**

Após a implementação:

✔ geração de relatório analítico dinâmico\
✔ documento oficial para uso institucional\
✔ integração total com os filtros\
✔ apoio à tomada de decisão estratégica\
✔ melhoria da governança da informação

Sem impacto nos módulos:

-   Alertas

-   Processamento territorial

-   Auditoria

-   Controle de acesso

**9.14.10 Classificação da Manutenção**

Tipo: **Manutenção Evolutiva**

Justificativa:

-   inclusão de nova funcionalidade analítica

-   sem alteração de comportamento existente

**9.14.11 Conformidade Arquitetural**

O ajuste mantém aderência a:

-   Arquitetura em camadas

-   Padrão MVC

-   Governança de dados

-   Princípio da responsabilidade única

-   ISO/IEC 12207

**9.14.12 Benefícios Obtidos**

-   Transformação do sistema em plataforma de inteligência analítica
    documental

-   Geração de relatórios estratégicos oficiais

-   Suporte a planejamento operacional

-   Base para integração futura com BI

9.15 Rastreabilidade dos Arquivos Impactados

A Tarefa Evolutiva 07 envolveu a criação de novos componentes e a
integração com módulos analíticos já existentes, sem alteração
estrutural nas regras de negócio dos serviços previamente implementados.

A tabela a seguir apresenta a rastreabilidade dos arquivos impactados,
indicando sua função no sistema e o tipo de modificação realizada.

Tabela 27 -- Rastreabilidade dos Arquivos Impactados -- Relatório
Analítico Multirriscos

  -------------------------------------------------------------------------------------------------------------------------
  **CAMADA**   **ARQUIVO**                                            **TIPO DE    **DESCRIÇÃO DA             **IMPACTO**
                                                                      AÇÃO**       MODIFICAÇÃO**              
  ------------ ------------------------------------------------------ ------------ -------------------------- -------------
  Frontend     /pages/analises/js/analise-global.js                   Alteração    Implementação dos filtros  Baixo
                                                                                   dinâmicos, abertura do     
                                                                                   modal analítico, consumo   
                                                                                   da API e envio dos         
                                                                                   parâmetros para geração do 
                                                                                   PDF                        

  Frontend     /pages/analises/index.php *(ou página correspondente   Alteração    Inclusão dos componentes   Baixo
               da análise)*                                                        de filtro, botão de        
                                                                                   geração do relatório e     
                                                                                   estrutura do modal         

  Backend --   /pages/analises/api/filtros_base.php                   Alteração    Disponibilização dos dados Baixo
  API                                                                              dinâmicos de anos, regiões 
                                                                                   e municípios para os       
                                                                                   filtros                    

  Backend --   /pages/analises/api/analise_global.php                 Criação      Endpoint responsável por   Médio
  API                                                                              consolidar os dados        
                                                                                   analíticos conforme os     
                                                                                   filtros selecionados       

  Backend --   /pages/analises/pdf/relatorio_analitico.php            Criação      Orquestra a geração do     Médio
  Controller                                                                       relatório analítico em PDF 
  de PDF                                                                           a partir dos parâmetros    
                                                                                   recebidos                  

  Service      /app/Services/AnaliseGlobalService.php                 Alteração    Consolidação das análises  Médio
                                                                                   de severidade,             
                                                                                   temporalidade, tipologia e 
                                                                                   índices de risco em uma    
                                                                                   única estrutura de dados   

  Service      /app/Services/RelatorioAnaliticoPdfService.php         Criação      Responsável pela montagem  Alto
                                                                                   do layout institucional e  
                                                                                   renderização do PDF        
                                                                                   analítico multirriscos     

  Service      /app/Services/AnaliseSeveridadeService.php             Integração   Consumo dos dados para     Baixo
                                                                                   composição do relatório    
                                                                                   analítico                  

  Service      /app/Services/AnaliseMunicipiosImpactadosService.php   Integração   Consumo dos dados para     Baixo
                                                                                   composição do relatório    
                                                                                   analítico                  

  Service      /app/Services/AnaliseAlertasEmitidosService.php        Integração   Consumo dos dados para     Baixo
                                                                                   composição do relatório    
                                                                                   analítico                  

  Service      /app/Services/AnaliseTemporalService.php               Integração   Consumo dos dados para     Baixo
                                                                                   composição do relatório    
                                                                                   analítico                  

  Service      /app/Services/AnaliseTipologiaService.php              Integração   Consumo dos dados para     Baixo
                                                                                   composição do relatório    
                                                                                   analítico                  

  Service      /app/Services/AnaliseIndiceRiscoService.php            Integração   Consumo dos dados para     Baixo
                                                                                   composição do relatório    
                                                                                   analítico (IRP e IPT)      

  Core         /app/Core/Database.php                                 Reuso        Utilizado para conexão com Nulo
                                                                                   o banco de dados           

  Biblioteca   /app/Lib/dompdf/                                       Reuso        Utilizada para             Nulo
  externa                                                                          renderização do documento  
                                                                                   PDF                        

  Assets       /assets/images/\*.png                                  Integração   Logotipos institucionais   Nulo
                                                                                   utilizados na capa,        
                                                                                   cabeçalho e rodapé do      
                                                                                   relatório                  
  -------------------------------------------------------------------------------------------------------------------------

**9.15.1 Classificação do Impacto**

-   **Alto** → novo serviço crítico para geração do documento oficial

-   **Médio** → criação de novos pontos de entrada e consolidação de
    dados

-   **Baixo** → consumo de dados sem alteração de regra de negócio

-   **Nulo** → apenas reutilização de componente existente

**9.15.2 Rastreabilidade Funcional**

Funcionalidade implementada:

📄 Relatório Analítico Multirriscos

Arquivos diretamente responsáveis pela entrega da funcionalidade:

-   analise-global.js

-   analise_global.php

-   RelatorioAnaliticoPdfService.php

-   relatorio_analitico.php

Arquivos de suporte (provedores de dados):

-   Serviços analíticos existentes

9.16 -- Evidências de Testes e Validação da Funcionalidade

A validação da Tarefa Evolutiva 07 foi realizada por meio de testes
funcionais, testes de integração entre camadas (Frontend → API →
Services → PDF) e verificação visual do documento gerado, garantindo a
consistência dos dados apresentados no modal e no relatório em PDF.

Os testes consideraram diferentes combinações de filtros, incluindo
cenários com ausência de parâmetros, de modo a assegurar o correto
tratamento das regras de negócio.

Tabela 28 -- Testes Funcionais dos Filtros do Relatório Analítico

  --------------------------------------------------------------------------------
  **CENÁRIO**   **ENTRADA**    **PROCESSAMENTO       **RESULTADO      **STATUS**
                               ESPERADO**            OBTIDO**         
  ------------- -------------- --------------------- ---------------- ------------
  Sem seleção   Ano, mês,      Sistema deve          Dados            ✅ Aprovado
  de filtros    região e       considerar todos os   consolidados     
                município      registros             exibidos no      
                vazios                               modal e no PDF   

  Filtro por    Ano específico Dados filtrados       Informações      ✅ Aprovado
  ano           selecionado    apenas para o ano     apresentadas     
                               informado             corretamente     

  Filtro por    Ano e mês      Aplicação do recorte  Dados exibidos   ✅ Aprovado
  mês           selecionados   temporal mensal       conforme o       
                                                     período          

  Filtro por    Região         Restrição territorial Apenas           ✅ Aprovado
  região        selecionada    aplicada              municípios da    
                                                     região exibidos  

  Filtro por    Município      Exibição dos dados    Resultado        ✅ Aprovado
  município     selecionado    apenas do município   correspondente   
                                                     ao filtro        

  Troca         Alteração do   Recarregamento da     Municípios       ✅ Aprovado
  dinâmica de   filtro de      lista de municípios   atualizados      
  região        região                               corretamente     
  --------------------------------------------------------------------------------

Tabela 29 -- Testes do Modal Analítico

  ---------------------------------------------------------------------------------
  **CENÁRIO**    **AÇÃO DO       **RESULTADO           **RESULTADO     **STATUS**
                 USUÁRIO**       ESPERADO**            OBTIDO**        
  -------------- --------------- --------------------- --------------- ------------
  Abertura do    Clique em       Exibição do modal com Modal exibido   ✅ Aprovado
  modal          "Gerar          indicador de          corretamente    
                 Relatório"      carregamento                          

  Fechamento por Clique no botão Modal encerrado       Funcionamento   ✅ Aprovado
  botão          fechar                                normal          

  Fechamento por Pressionar ESC  Modal encerrado       Funcionamento   ✅ Aprovado
  tecla ESC                                            normal          

  Fechamento ao  Clique fora do  Modal encerrado       Funcionamento   ✅ Aprovado
  clicar fora    modal                                 normal          

  Renderização   Retorno da API  Estrutura montada com Dados exibidos  ✅ Aprovado
  dos dados                      todas as seções       corretamente    
  ---------------------------------------------------------------------------------

Tabela 30 -- Testes de Consistência entre Modal e PDF

  --------------------------------------------------------------------------
  **ELEMENTO**                       **MODAL**   **PDF**    **RESULTADO**
  ---------------------------------- ----------- ---------- ----------------
  Parâmetros do relatório            Exibidos    Exibidos   Consistente

  Evolução anual de alertas          Exibido     Exibido    Consistente

  Distribuição por severidade        Exibido     Exibido    Consistente

  Municípios mais impactados         Exibido     Exibido    Consistente

  Quantidade de alertas por evento   Exibido     Exibido    Consistente

  Duração média por evento           Exibido     Exibido    Consistente

  Sazonalidade mensal                Exibido     Exibido    Consistente

  Comparativo de eventos             Exibido     Exibido    Consistente

  Frequência por período             Exibido     Exibido    Consistente

  Correlação tipologia × severidade  Exibido     Exibido    Consistente

  Tipologia por região               Exibido     Exibido    Consistente

  IRP                                Exibido     Exibido    Consistente

  IPT                                Exibido     Exibido    Consistente
  --------------------------------------------------------------------------

Tabela 31 -- Testes de Geração do PDF

  ---------------------------------------------------------------------------
  **CENÁRIO**           **RESULTADO ESPERADO** **RESULTADO       **STATUS**
                                               OBTIDO**          
  --------------------- ---------------------- ----------------- ------------
  Geração sem filtros   PDF completo com todos Gerado com        ✅ Aprovado
                        os dados               sucesso           

  Geração com filtros   PDF respeitando os     Gerado            ✅ Aprovado
                        parâmetros             corretamente      

  Nome do arquivo       Nome com data e        Aplicado          ✅ Aprovado
  dinâmico              horário                corretamente      

  Capa institucional    Página exclusiva       Renderizada       ✅ Aprovado
                                               corretamente      

  Cabeçalho e rodapé    Repetição automática   Funcionando       ✅ Aprovado

  Logotipos             Exibição correta       Funcionando       ✅ Aprovado
  institucionais                                                 

  Numeração de páginas  Sequencial             Funcionando       ✅ Aprovado

  Quebra de páginas por Aplicada corretamente  Funcionando       ✅ Aprovado
  seção                                                          

  Subtítulos            Não separar da tabela  Funcionando       ✅ Aprovado
  acompanhando tabelas                                           
  ---------------------------------------------------------------------------

**Tabela 32 -- Testes de Desempenho**

  -----------------------------------------------------------------------
  **CENÁRIO**                     **RESULTADO**
  ------------------------------- ---------------------------------------
  Geração com grande volume de    Tempo dentro do aceitável
  dados                           

  Consumo de memória              Otimizado com limitação de registros
                                  nas tabelas

  Renderização do DOMPDF          Estável após ajustes de configuração
  -----------------------------------------------------------------------

**9.16.1 Validação da Integridade dos Dados**

Foi realizada a conferência entre:

-   Dados retornados pela API

-   Dados exibidos no modal

-   Dados renderizados no PDF

Garantindo:

✔ integridade\
✔ rastreabilidade\
✔ ausência de divergência de valores

**9.16.2 Validação Visual do Documento**

Itens verificados:

-   Estrutura institucional do relatório

-   Padronização tipográfica

-   Alinhamento das tabelas

-   Hierarquia dos títulos e subtítulos

-   Capa institucional

-   Sumário

-   Paginação

**✅ Resultado da Validação**

A funcionalidade foi considerada **APROVADA**, atendendo:

-   aos requisitos funcionais

-   aos requisitos de apresentação institucional

-   às regras de filtragem

-   à consistência entre modal e PDF

**9.17 -- Controle de Versão da Funcionalidade / Histórico da
Implementação**

O controle de versão da Tarefa Evolutiva 07 foi realizado com o objetivo
de garantir a rastreabilidade das alterações efetuadas no sistema,
permitindo identificar com precisão:

-   os componentes modificados

-   a natureza da alteração

-   a motivação técnica

-   o impacto funcional

Esse controle assegura a manutenção evolutiva, auditoria das mudanças e
rollback seguro, caso necessário.

**Tabela 33 -- Histórico de Implementação da Funcionalidade**

  -----------------------------------------------------------------------------------------------------
  **VERSÃO**   **DATA**             **DESCRIÇÃO DA ALTERAÇÃO**  **TIPO**            **RESPONSÁVEL**
  ------------ -------------------- --------------------------- ------------------- -------------------
  **7.0.0**    ***/*/\_\_\_\_\_**   **Criação da estrutura base **Implementação**   **Equipe de
                                    do relatório analítico                          Desenvolvimento**
                                    multirriscos**                                  

  **7.0.1**    ***/*/\_\_\_\_\_**   **Implementação dos filtros **Evolutiva**       **Equipe de
                                    (ano, mês, região e                             Desenvolvimento**
                                    município)**                                    

  **7.0.2**    ***/*/\_\_\_\_\_**   **Integração com API        **Evolutiva**       **Equipe de
                                    analise_global.php**                            Desenvolvimento**

  **7.0.3**    ***/*/\_\_\_\_\_**   **Criação do modal de       **Evolutiva**       **Equipe de
                                    visualização do relatório**                     Desenvolvimento**

  **7.0.4**    ***/*/\_\_\_\_\_**   **Renderização dinâmica das **Evolutiva**       **Equipe de
                                    tabelas no modal**                              Desenvolvimento**

  **7.0.5**    ***/*/\_\_\_\_\_**   **Implementação da          **Evolutiva**       **Equipe de
                                    exportação em PDF**                             Desenvolvimento**

  **7.0.6**    ***/*/\_\_\_\_\_**   **Padronização do layout    **Melhoria**        **Equipe de
                                    institucional do PDF**                          Desenvolvimento**

  **7.0.7**    ***/*/\_\_\_\_\_**   **Inclusão da capa          **Melhoria**        **Equipe de
                                    institucional**                                 Desenvolvimento**

  **7.0.8**    ***/*/\_\_\_\_\_**   **Inserção de cabeçalho e   **Melhoria**        **Equipe de
                                    rodapé com logotipos**                          Desenvolvimento**

  **7.0.9**    ***/*/\_\_\_\_\_**   **Implementação da          **Melhoria**        **Equipe de
                                    paginação automática**                          Desenvolvimento**

  **7.0.10**   ***/*/\_\_\_\_\_**   **Quebra de páginas por     **Melhoria**        **Equipe de
                                    seção**                                         Desenvolvimento**

  **7.0.11**   ***/*/\_\_\_\_\_**   **Ajuste para evitar quebra **Correção**        **Equipe de
                                    de tabelas entre páginas**                      Desenvolvimento**

  **7.0.12**   ***/*/\_\_\_\_\_**   **Sincronização estrutural  **Melhoria**        **Equipe de
                                    entre modal e PDF**                             Desenvolvimento**

  **7.0.13**   ***/*/\_\_\_\_\_**   **Correção do fuso horário  **Correção**        **Equipe de
                                    para padrão local**                             Desenvolvimento**

  **7.0.14**   ***/*/\_\_\_\_\_**   **Nome do arquivo PDF com   **Melhoria**        **Equipe de
                                    data e horário da geração**                     Desenvolvimento**

  **7.0.15**   ***/*/\_\_\_\_\_**   **Ajuste do layout das      **Correção**        **Equipe de
                                    tabelas de tipologia                            Desenvolvimento**
                                    (correlação e por região)**                     

  **7.0.16**   ***/*/\_\_\_\_\_**   **Otimização de consumo de  **Melhoria**        **Equipe de
                                    memória na geração do PDF**                     Desenvolvimento**
  -----------------------------------------------------------------------------------------------------

**9.17.1 Padrão de Versionamento**

Foi adotado o versionamento no formato:

MAJOR.MINOR.PATCH

Onde:

-   MAJOR → alterações estruturais ou novas funcionalidades completas

-   MINOR → melhorias e evoluções funcionais

-   PATCH → correções de erro ou ajustes pontuais

**9.17.2 Estratégia de Atualização**

As atualizações seguiram o fluxo:

1.  Implementação em ambiente de desenvolvimento

2.  Validação funcional

3.  Testes de integração

4.  Testes de geração de PDF

5.  Publicação em produção

**9.17.3 Itens Versionados**

Foram controladas as alterações nos seguintes componentes:

-   Scripts JavaScript do módulo de análise

-   APIs de processamento analítico

-   Services de agregação de dados

-   Service de geração do PDF

-   Estrutura HTML/CSS do relatório

-   Regras de negócio dos filtros

**9.17.4 Benefícios do Controle de Versão**

-   Rastreabilidade completa das alterações

-   Segurança em manutenções futuras

-   Facilidade de auditoria do sistema

-   Padronização do processo evolutivo

-   Possibilidade de rollback controlado

✅ Situação da Funcionalidade

A Tarefa Evolutiva 07 encontra-se:

✔ Implementada\
✔ Versionada\
✔ Validada\
✔ Documentada

**9.18 -- Impactos da Evolução no Sistema**

A implementação da Tarefa Evolutiva 07 -- Módulo de Análise e Relatório
Analítico Multirriscos produziu impactos controlados na arquitetura do
sistema, abrangendo as camadas de apresentação, aplicação e dados, sem
comprometer as funcionalidades previamente existentes.

A evolução foi conduzida com foco em baixo acoplamento, alta coesão e
reaproveitamento de serviços, mantendo a estabilidade do ambiente em
produção.

**9.18.1 Impactos na Camada de Apresentação (Front-end)**

Foram inseridos novos componentes de interface para permitir a interação
do usuário com o módulo analítico.

Principais impactos:

-   Inclusão dos filtros dinâmicos:

    -   Ano

    -   Mês

    -   Região de Integração

    -   Município

-   Implementação do modal de visualização do relatório

-   Criação do botão de exportação em PDF

-   Renderização dinâmica das tabelas analíticas via JavaScript

Resultado:

-   Não houve alteração nas telas existentes

-   A funcionalidade foi isolada dentro do módulo de análise

-   Interface mais responsiva e orientada a dados

**9.18.2 Impactos na Camada de Aplicação (Back-end)**

A camada de serviços foi expandida com a criação de componentes
especializados para processamento analítico.

Services adicionados:

-   AnaliseGlobalService

-   AnaliseTemporalService

-   AnaliseTipologiaService

-   AnaliseIndiceRiscoService

-   RelatorioAnaliticoPdfService

Principais efeitos:

-   Centralização da regra de negócio analítica

-   Reutilização de consultas SQL já otimizadas

-   Padronização do retorno de dados em estrutura única

Não houve:

-   alteração em regras de negócio operacionais

-   impacto nas rotinas transacionais do sistema

**9.18.3 Impactos na Camada de Dados**

Não foram necessárias alterações estruturais no banco de dados.

A evolução utilizou exclusivamente:

-   tabelas já existentes

-   relacionamentos previamente implementados

-   consultas agregadas para análise estatística

**Consequência:**

-   risco zero de migração de dados

-   nenhuma indisponibilidade do sistema

**9.18.4 Impacto no Desempenho**

A geração do relatório analítico introduziu processamento adicional,
especialmente:

-   consultas agregadas

-   montagem de estruturas multidimensionais

-   renderização do PDF

**Medidas adotadas para mitigação:**

-   Limitação de registros exibidos nas tabelas

-   Desativação de recursos pesados do DOMPDF

-   Conversão de imagens para Base64 apenas quando necessário

-   Aumento controlado do memory_limit para geração do PDF

**Resultado:**

-   Processamento sob demanda

-   Nenhum impacto na navegação do sistema

-   Execução isolada em nova aba

**9.18.5 Impacto na Usabilidade**

A evolução trouxe ganhos significativos:

-   Visualização consolidada dos dados

-   Redução do tempo de análise operacional

-   Tomada de decisão baseada em indicadores

-   Exportação de relatório institucional padronizado

**9.18.6 Impacto na Manutenibilidade**

A nova estrutura baseada em services independentes proporcionou:

-   Facilidade de manutenção

-   Baixo acoplamento

-   Facilidade para inclusão de novos indicadores

-   Reutilização do motor analítico em outros módulos

**9.18.7 Impacto na Escalabilidade**

A arquitetura implementada permite:

-   inclusão de novos tipos de análise

-   geração de novos relatórios

-   integração com painéis de BI

**sem necessidade de refatoração estrutural.**

**9.18.8 Impacto na Segurança**

A funcionalidade:

-   utiliza os mesmos padrões de acesso já existentes

-   não expõe dados sensíveis

-   realiza apenas consultas de leitura

Mantendo a conformidade com o modelo de segurança do sistema.

**9.18.9 Síntese dos Impactos**

  -----------------------------------------------------------------------
  **ASPECTO**            **IMPACTO**
  ---------------------- ------------------------------------------------
  Arquitetura            Positivo -- modularização por services

  Banco de dados         Nenhum

  Desempenho             Controlado e sob demanda

  Interface              Positivo -- nova capacidade analítica

  Segurança              Mantida

  Manutenibilidade       Alta

  Escalabilidade         Alta
  -----------------------------------------------------------------------

**✅ Conclusão**

A Tarefa Evolutiva 07 ampliou a capacidade analítica do Sistema
Inteligente Multirriscos sem causar impactos negativos nas
funcionalidades existentes, mantendo a estabilidade, segurança e
desempenho da aplicação.

**A evolução consolidou o sistema como uma plataforma de apoio à decisão
baseada em dados, elevando seu nível de maturidade tecnológica.**

9.19 Ajuste Evolutivo 08 – ajustes no filtro e status do analise multirriscos

9.19 – Identificação da Necessidade do Ajuste
Durante a evolução do módulo de Análises Multirriscos foi identificado um desvio conceitual e estatístico nos indicadores: os alertas com status CANCELADO estavam sendo somados aos demais nas consultas analíticas, distorcendo totais, índices e séries temporais.
Além disso, parte das consultas do relatório analítico (modal e PDF) não estava respeitando os filtros globais (ano, mês, região e município), resultando em:
•	inconsistência entre telas
•	divergência entre gráficos e tabelas
•	perda de confiabilidade analítica
Também foram detectados:
•	erro 500 na página de Análise Temporal após refatoração para o novo padrão com $filtro
•	quebra dos gráficos por saída indevida de debug dentro da tag <script>
•	método não implementado após refatoração do service temporal
•	falha lógica no filtro regional do IRP
9.20 – Objetivo do Ajuste Evolutivo
Implementar a padronização definitiva do tratamento de status dos alertas nas análises, garantindo que:
•	indicadores considerem apenas ATIVO e ENCERRADO
•	CANCELADOS sejam contabilizados separadamente
•	todos os dados analíticos respeitem os filtros globais
•	o padrão de service orientado a $filtro seja aplicado ao módulo temporal
•	modal e PDF utilizem a mesma base de dados analítica
9.21 – Escopo do Ajuste
Backend
Refatoração dos services:
•	AnaliseTemporalService
•	AnaliseSeveridadeService
•	AnaliseTipologiaService
•	AnaliseIndiceRiscoService
•	AnaliseAlertasEmitidosService
•	AnaliseMunicipiosImpactadosService
•	AnaliseGlobalService
Frontend
•	página temporal.php
•	modal analítico (analise-global.js)
•	gráficos Chart.js
Relatórios
•	inclusão de cancelados por ano no modal
•	inclusão de cancelados por ano no PDF
•	coerência com os filtros selecionados
9.22 – Regras de Negócio Implementadas
1.	Indicadores operacionais e estratégicos:
status considerados = ATIVO, ENCERRADO
2.	Cancelados:
•	não entram nos totais analíticos
•	apresentados separadamente por ano
3.	Filtros globais aplicáveis a:
•	severidade
•	tipologia
•	municípios impactados
•	índices de risco
•	análises temporais
4.	Evolução anual passa a possuir duas leituras:
•	alertas válidos
•	alertas cancelados
9.23 – Refatoração da Camada de Serviços
Padronização de assinatura:
metodo(PDO $db, array $filtro)
Criação de estrutura de aplicação de filtros:
•	ano
•	mês
•	região
•	município
Remoção de queries sem contexto analítico.
9.24 – Correções de Bugs
Erro 500 na análise temporal
Causas:
•	métodos removidos na refatoração
•	chamada com assinatura antiga
•	variáveis não inicializadas
Ações:
•	recriação dos métodos:
o	listaEventos()
o	sazonalidadeMensalPorEvento()
o	evolucaoAnualPorEvento($filtro)
Quebra dos gráficos (Unexpected token '<')
Causa:
•	uso de print_r() dentro do <script>
Correção:
•	remoção de qualquer saída de debug no HTML
•	uso de error_log() para inspeção
Filtro regional no IRP
Causa:
•	ausência de aplicação do filtro territorial na query
Correção:
•	inclusão do vínculo com alerta_municipios + municipios_regioes_pa
9.25 – Novas Funcionalidades Implementadas
Análise Temporal
•	gráfico de alertas cancelados por ano
•	evolução anual por tipo de evento com filtro
•	compatibilidade total com o modal
Modal Analítico
•	passa a respeitar integralmente os filtros globais
•	inclusão da informação de cancelados por ano
PDF Analítico
Nova informação:
•	cancelados por ano no sumário
•	cancelados na seção temporal
9.26 – Impacto na Arquitetura
Positivo
•	unificação da fonte de dados analítica
•	eliminação de divergência entre telas
•	aumento da confiabilidade dos indicadores
•	padronização da camada de services
Controlado
•	alteração apenas na camada de consulta
•	nenhuma mudança estrutural no banco
9.27 – Rastreabilidade dos Arquivos Impactados
Services
•	AnaliseTemporalService.php
•	AnaliseSeveridadeService.php
•	AnaliseTipologiaService.php
•	AnaliseIndiceRiscoService.php
•	AnaliseAlertasEmitidosService.php
•	AnaliseMunicipiosImpactadosService.php
•	AnaliseGlobalService.php
Pages
•	pages/analises/temporal.php
Frontend
•	assets/js/analise-global.js
Relatórios
•	RelatorioAnaliticoPdfService.php
9.28 – Evidências de Testes e Validação
Testes realizados:
1.	Filtro por ano
2.	Filtro por região
3.	Filtro por município
4.	Filtro combinado
5.	Comparação:
•	tela temporal
•	modal
•	PDF
Resultados:
•	totais coerentes entre todas as interfaces
•	cancelados não interferem nos indicadores
•	gráficos renderizando corretamente
•	ausência de erro 500
9.29 – Controle de Versão da Funcionalidade
Versão lógica: Ajuste Evolutivo 08
Tipo:
•	refatoração estrutural
•	correção de regra de negócio
•	ampliação analítica
9.30 – Ganhos Operacionais
•	confiabilidade estatística dos indicadores
•	leitura estratégica real dos dados
•	coerência entre painéis e relatórios
•	base preparada para:
o	IA analítica
o	séries históricas
o	modelos preditivos
9.31 – Pendências Planejadas (Próxima Evolução)
•	consolidação da query base temporal única com:
o	válidos
o	cancelados
o	por tipo de evento
•	cache analítico
•	exportação de dados estruturados
•	camada de indicadores estratégicos derivados


## **10. REFERÊNCIAS BIBLIOGRÁFICAS**

BRASIL. **Lei nº 12.608, de 10 de abril de 2012**. Institui a Política
Nacional de Proteção e Defesa Civil -- PNPDEC. Diário Oficial da União:
seção 1, Brasília, DF, 11 abr. 2012.

BRASIL. **Decreto nº 10.593, de 24 de dezembro de 2020**. Dispõe sobre a
organização e o funcionamento do Sistema Nacional de Proteção e Defesa
Civil -- SINPDEC. Diário Oficial da União: seção 1, Brasília, DF, 28
dez. 2020.

UNITED NATIONS. **Sendai Framework for Disaster Risk Reduction
2015--2030**. Geneva: United Nations Office for Disaster Risk Reduction,
2015.

INTERNATIONAL ORGANIZATION FOR STANDARDIZATION. **ISO/IEC 25010:2011**.
Systems and software engineering -- Systems and software Quality
Requirements and Evaluation (SQuaRE) -- System and software quality
models. Geneva: ISO, 2011.

INTERNATIONAL ORGANIZATION FOR STANDARDIZATION. **ISO/IEC 12207:2017**.
Systems and software engineering -- Software life cycle processes.
Geneva: ISO, 2017.

INTERNATIONAL ORGANIZATION FOR STANDARDIZATION. **ISO 19115-1:2014**.
Geographic information -- Metadata -- Part 1: Fundamentals. Geneva: ISO,
2014.

INTERNATIONAL ORGANIZATION FOR STANDARDIZATION. **ISO 19107:2003**.
Geographic information -- Spatial schema. Geneva: ISO, 2003.

INSTITUTE OF ELECTRICAL AND ELECTRONICS ENGINEERS. **IEEE Std
830-1998**. Recommended Practice for Software Requirements
Specifications. New York: IEEE, 1998.

INSTITUTE OF ELECTRICAL AND ELECTRONICS ENGINEERS. **IEEE Std
29148-2018**. Systems and software engineering -- Life cycle processes
-- Requirements engineering. New York: IEEE, 2018.

OPEN WORLDWIDE APPLICATION SECURITY PROJECT. **OWASP Top 10 -- Web
Application Security Risks**. \[S.l.\]: OWASP Foundation, 2021.

OPEN GEOSPATIAL CONSORTIUM. **OGC Standards and Specifications**.
Wayland, MA: OGC, 2023.

PRESSMAN, Roger S.; MAXIM, Bruce R. **Engenharia de Software: uma
abordagem profissional**. 8. ed. Porto Alegre: AMGH, 2016.

SOMMERVILLE, Ian. **Engenharia de Software**. 10. ed. São Paulo: Pearson
Education do Brasil, 2018.

ELMASRI, Ramez; NAVATHE, Shamkant B. **Sistemas de Banco de Dados**. 7.
ed. São Paulo: Pearson, 2019.

SILBERSCHATZ, Abraham; KORTH, Henry F.; SUDARSHAN, S. **Sistema de Banco
de Dados**. 6. ed. Rio de Janeiro: Elsevier, 2012.

LONGLEY, Paul A. et al. **Geographic Information Systems and Science**.
4th ed. Hoboken: Wiley, 2015.

POWER, Daniel J. **Decision Support Systems: Concepts and Resources for
Managers**. Westport: Quorum Books, 2002.

BRASIL. Ministério da Integração e do Desenvolvimento Regional. **Manual
de Proteção e Defesa Civil**. Brasília, DF: MIDR, 2020.

CENTRO NACIONAL DE MONITORAMENTO E ALERTAS DE DESASTRES NATURAIS
(CEMADEN). **Diretrizes Técnicas para Monitoramento e Alerta de
Desastres Naturais**. São José dos Campos, 2022.

AGÊNCIA NACIONAL DE ÁGUAS E SANEAMENTO BÁSICO (ANA). **Monitoramento
Hidrológico e Gestão de Riscos**. Brasília, DF, 2021.

SERVIÇO GEOLÓGICO DO BRASIL (SGB). **Mapeamento de Áreas de Risco
Geológico**. Brasília, DF, 2020.
