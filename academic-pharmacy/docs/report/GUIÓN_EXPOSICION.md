# Guion de exposición — Kay Care Farmacia (8 a 10 minutos)

**Curso:** Servicios Cloud · Universidad Tecnológica del Perú
**Expositor:** Elias Paz Benavides

## Antes de empezar (5 minutos antes)

1. Abrir Docker Desktop y, en una terminal, ejecutar:

   ```bash
   cd academic-pharmacy
   docker compose up -d
   ```

2. Abrir en el navegador http://localhost:8088. Si ese día se autorizó y verificó el despliegue en Azure, usar la URL que mostró `deploy-azure.ps1`.
3. Tener abiertas tres pestañas:
   - la tienda;
   - el repositorio en GitHub (`academic-pharmacy/`);
   - el informe en PDF, en la página de diagramas.
4. Tener abiertos en el editor: `academic-pharmacy/docker-compose.yml`, `academic-pharmacy/infra/terraform/environments/demo/main.tf` y `.github/workflows/pharmacy-ci.yml`.
5. Vaciar el carrito de la tienda para la demo.

> Consejo: habla mirando al profesor, no a la pantalla. Lo que va entre corchetes es lo que haces; lo demás es lo que dices.

## 1. Presentación del proyecto (0:00 – 0:45)

[Mostrar página principal]

«Buenos días, profesor. Mi proyecto se llama **Kay Care Farmacia**. Es una plataforma de comercio electrónico farmacéutico, construida para demostrar lo que vimos en Servicios Cloud: contenedores, infraestructura como código, automatización, seguridad y alta disponibilidad.

Es un proyecto académico: los productos y las marcas son ficticios, no se cobra nada y no se piden datos personales. Por eso ven arriba este aviso: *Proyecto académico, no se realizan ventas reales*.»

## 2. Problemática (0:45 – 1:35)

«¿Qué problema quisimos abordar? Pensemos en una farmacia de barrio que quiere vender por internet. Se encuentra con varias barreras:
- comprar y mantener servidores cuesta dinero;
- una tienda en línea tiene muchas piezas técnicas;
- el inventario se puede descuadrar;
- si el único servidor se cae, se acaban las ventas.

Y cada vez que se actualiza la página, alguien tiene que hacerlo a mano.

Aclaro que es un escenario académico: Kay Care no es una empresa real ni hicimos encuestas. Tomamos esos desafíos generales del sector y nos preguntamos: *¿cómo implementar una tienda así con servicios cloud, contenedores e infraestructura como código, que sea segura, escalable y esté preparada para alta disponibilidad?*»

## 3. Objetivos (1:35 – 2:05)

«Nos propusimos cinco cosas:
1. una tienda funcional conectada a una base de datos real;
2. que nunca venda un producto sin stock;
3. empaquetarla en contenedores Docker;
4. describir la infraestructura de Azure con Terraform;
5. automatizar las pruebas con GitHub Actions, con un despliegue que solo se hace manualmente y con autorización.»

## 4. Solución propuesta (2:05 – 2:35)

[Mostrar diagrama de arquitectura — Figura 1 del informe]

«La solución tiene tres capas.
- El usuario entra desde el navegador a una aplicación hecha en **Vue**.
- Vue le pide los datos a una **API en Laravel**, que es donde están las reglas del negocio.
- Laravel guarda todo en **PostgreSQL**.

Delante hay un **nginx** que sirve la página y reenvía las llamadas a la API por el mismo dominio. Eso simplifica mucho la seguridad.»

## 5. Demostración de la tienda (2:35 – 4:35)

[Mostrar página principal y bajar despacio por las secciones]

«Esta es la página de inicio. Tiene categorías, ofertas y productos destacados. Todo lo que ven viene de la base de datos, no es una página estática.»

[Escribir «vitamina» en el buscador]

«El buscador muestra sugerencias mientras escribo: cada letra consulta la API en tiempo real.»

[Abrir la categoría «Primeros auxilios» o un producto en oferta]

«Aquí está el detalle del producto, con precio, precio anterior si está en oferta y stock disponible.»

[Agregar producto al carrito; subir la cantidad a 2]

«Lo agrego al carrito. El carrito no solo suma: le pregunta al servidor si todavía hay stock y recalcula los precios. El navegador nunca decide el precio.»

[Ir al checkout y escribir 45879632 como alias]

«En el checkout no pedimos tarjeta ni dirección, solo un alias ficticio. Si escribo algo que parece un DNI, el sistema lo rechaza para no guardar datos personales.»

[Cambiar el alias por «Comprador Demo», aceptar el aviso y confirmar]

«Ahora sí: *Pedido realizado en entorno de demostración*. El pedido quedó guardado en PostgreSQL y el stock se descontó.»

[Opcional, si sobra tiempo: abrir /admin, mostrar el pedido en «Pedidos»]

## 6. Arquitectura y el problema del stock (4:35 – 5:15)

[Mostrar el diagrama de flujo — Figura 2b]

«Lo más interesante técnicamente es esto: ¿qué pasa si dos personas compran la última unidad al mismo tiempo? Laravel abre una **transacción** y **bloquea las filas** de esos productos hasta terminar. Así, la segunda compra espera y ve el stock real.

Además, cada compra lleva una **clave de idempotencia**: si se cae la red y el usuario reintenta, el servidor devuelve el mismo pedido en vez de duplicarlo.

Lo probamos: 9 compras simultáneas con stock 4. Se crearon 4 pedidos, se rechazaron 5 y el stock quedó en 0, sin sobreventa.»

## 7. Docker (5:15 – 6:00)

[Mostrar Docker: terminal con `docker compose ps` y el archivo docker-compose.yml]

«Todo corre en contenedores. Docker Compose levanta tres servicios: la base de datos, la API y el frontend. Con un solo comando, `docker compose up --build`, cualquiera levanta el sistema completo en su máquina.

Algunos detalles de seguridad:
- los contenedores no corren como root;
- su sistema de archivos es de solo lectura;
- la base de datos está en una red interna, sin salida a internet;
- los datos persisten en un volumen: si apago y prendo los contenedores, los pedidos siguen ahí.»

## 8. Terraform (6:00 – 6:50)

[Mostrar archivo main.tf de environments/demo]

«La infraestructura de Azure está escrita como código con **Terraform**. En vez de crear recursos a mano en el portal, este archivo describe:
- un grupo de recursos propio;
- Azure Container Apps para el frontend y la API;
- PostgreSQL Flexible Server;
- Log Analytics para los logs.

Hay dos entornos. DEMO es el más barato posible: escala a cero cuando no hay visitas. HA es el de alta disponibilidad.

Ambos pasan `terraform validate`. Pero, para ser honesto: **todavía no ejecutamos `terraform apply`**. El despliegue real está reservado para hoy, si usted lo autoriza, porque crea recursos que consumen créditos. Para ese momento preparamos un script que primero revisa todo en modo de solo lectura, muestra el costo y pide una confirmación escrita.»

## 9. Automatización (6:50 – 7:30)

[Mostrar pharmacy-ci.yml y la pestaña Actions de GitHub]

«Cada vez que subo cambios a GitHub, **GitHub Actions** ejecuta la integración continua:
- pruebas del backend y del frontend;
- validación de Terraform;
- construcción de las imágenes Docker;
- levanta todo con Compose y hace un smoke test.

Todo eso está en verde. El despliegue a Azure es otro workflow que **solo se puede ejecutar a mano**, desde la rama main, escribiendo una confirmación y con aprobación. Un push nunca puede crear recursos en Azure, y la propia CI verifica que siga siendo así.»

## 10. Seguridad (7:30 – 8:05)

«En seguridad aplicamos varias capas:
- validamos todo lo que llega a la API;
- el login del administrador tiene límite de intentos;
- usamos tokens CSRF;
- la política de seguridad de contenido (CSP) bloquea scripts externos;
- las contraseñas se guardan con bcrypt;
- las consultas a la base de datos son parametrizadas, contra inyección SQL;
- los secretos nunca se suben a GitHub.

En Azure, todo iría por HTTPS.»

## 11. Alta disponibilidad (8:05 – 8:50)

[Mostrar el diagrama de alta disponibilidad — Figura 8]

«Alta disponibilidad significa que la tienda siga funcionando aunque algo falle. El diseño HA pone varias réplicas de la API repartidas en tres zonas de disponibilidad, con un balanceador delante. La base de datos tiene un servidor de respaldo en otra zona que toma el control automáticamente.

Quiero distinguir tres cosas:
- la HA está **diseñada** en Terraform;
- la **probamos localmente**: con dos réplicas de la API apagamos una con tráfico y respondieron 179 de 180 solicitudes;
- **no la desplegamos en Azure**, porque costaría unos 650 a 700 dólares al mes.

El entorno DEMO no tiene alta disponibilidad, y no lo vamos a presentar como si la tuviera.»

## 12. Resultados (8:50 – 9:20)

«Resultados concretos:
- 70 pruebas del backend sobre PostgreSQL;
- 45 del frontend;
- 3 recorridos completos en navegador, sin errores de accesibilidad;
- prueba de concurrencia sin sobreventa;
- Terraform validado;
- la CI de GitHub en verde.

Y algo importante: el sistema de enfermería que comparte el repositorio no se tocó; sus 108 pruebas siguen pasando.

En costos, el día de la exposición el entorno DEMO cuesta alrededor de 60 centavos de dólar si se retira el mismo día. Hasta hoy, el consumo de créditos de Azure es cero.»

## 13. Conclusiones (9:20 – 10:00)

«Para cerrar, tres ideas:
1. Con contenedores e infraestructura como código, la nube se puede preparar y validar **antes** de pagar por ella.
2. Un problema real del comercio electrónico, vender sin stock, se resuelve con transacciones y bloqueo de filas, y se puede comprobar con pruebas.
3. La alta disponibilidad tiene un costo: hay que diseñarla bien y activarla solo cuando el negocio la necesite.

Muchas gracias. Quedo atento a sus preguntas.»

## Si el tiempo se acorta

- **Para quedar en 8 minutos:** omite la parte opcional del panel admin (sección 5) y resume la sección 10 en una frase.
- **Si falla el navegador o Docker:** muestra las capturas del informe (Figuras 11 a 20) y di: «Estas capturas son reales, generadas automáticamente con Playwright contra los contenedores».
