import { spawn, spawnSync } from "node:child_process";
import { randomBytes, randomUUID } from "node:crypto";
import {
  existsSync,
  readFileSync,
  writeFileSync,
  mkdirSync,
  openSync,
} from "node:fs";
import { dirname, join, resolve, delimiter } from "node:path";
import { fileURLToPath } from "node:url";
import { createServer } from "node:net";
import { setTimeout as delay } from "node:timers/promises";
import { parseEnv } from "node:util";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const backend = join(root, "backend");
const frontend = join(root, "frontend");
const local = join(root, ".local");
const php = process.env.PHP_BINARY || "php";
const node = process.execPath;
const baseEnv = {
  ...process.env,
  PATH: `${dirname(node)}${delimiter}${process.env.PATH || ""}`,
};
const utf8 = { encoding: "utf8" };
const children = new Set();
mkdirSync(local, { recursive: true });

function child(binary, args, options = {}) {
  const process = spawn(binary, args, {
    cwd: root,
    env: baseEnv,
    stdio: "inherit",
    windowsHide: true,
    ...options,
  });
  children.add(process);
  process.once("exit", () => children.delete(process));
  process.once("error", (error) => {
    process.startupError = error;
  });
  return process;
}
function run(binary, args, options = {}) {
  return new Promise((resolve, reject) => {
    const process = child(binary, args, options);
    process.once("error", reject);
    process.once("exit", (code) =>
      code === 0
        ? resolve()
        : reject(new Error(`Falló ${binary} (código ${code}).`)),
    );
  });
}
function stop() {
  for (const ownedProcess of children) {
    if (!ownedProcess.pid || ownedProcess.exitCode !== null) continue;
    if (process.platform === "win32") {
      // Only the tree rooted at a child created by this script; never match names or ports.
      spawnSync("taskkill", ["/PID", String(ownedProcess.pid), "/T", "/F"], {
        windowsHide: true,
        stdio: "ignore",
      });
    } else ownedProcess.kill();
  }
  children.clear();
}
process.on("SIGINT", () => {
  stop();
  process.exit(0);
});
process.on("SIGTERM", () => {
  stop();
  process.exit(0);
});
process.on("exit", stop);

function readEnv(path) {
  if (!existsSync(path)) return {};
  return parseEnv(readFileSync(path, "utf8"));
}
function setEnv(contents, key, value) {
  const line = `${key}=${value}`;
  return new RegExp(`^${key}=.*$`, "m").test(contents)
    ? contents.replace(new RegExp(`^${key}=.*$`, "m"), () => line)
    : `${contents.trimEnd()}\n${line}\n`;
}
function localEnv() {
  const values = readEnv(join(backend, ".env"));
  if (
    values.APP_ENV !== "local" ||
    values.KAYCARE_MODE !== "demo" ||
    values.DB_CONNECTION !== "sqlite" ||
    values.DB_URL
  ) {
    throw new Error(
      "El arranque automático exige .env local/demo con SQLite y sin DB_URL. No se modificó la configuración existente.",
    );
  }
  const database = resolve(
    backend,
    values.DB_DATABASE || "database/database.sqlite",
  );
  return {
    ...baseEnv,
    ...values,
    DB_DATABASE: database,
    DB_URL: "",
    APP_CONFIG_CACHE: join(local, "local-config-unused.php"),
  };
}
async function freePort(port) {
  await new Promise((resolve, reject) => {
    const probe = createServer();
    probe.once("error", () =>
      reject(
        new Error(
          `El puerto localhost:${port} está ocupado. Detén su servidor y vuelve a ejecutar; no se cerró ningún proceso ajeno.`,
        ),
      ),
    );
    probe.listen(port, "localhost", () => probe.close(resolve));
  });
}
async function ready(url, process) {
  for (let attempt = 0; attempt < 80; attempt++) {
    if (process.startupError) throw process.startupError;
    if (process.exitCode !== null)
      throw new Error(`El servidor no arrancó: ${url}`);
    try {
      if ((await fetch(url, { signal: AbortSignal.timeout(1000) })).ok) return;
    } catch {
      /* wait for startup */
    }
    await delay(250);
  }
  throw new Error(`El servidor no respondió: ${url}`);
}
async function servers(backendPort, frontendPort, env, logPrefix) {
  await freePort(backendPort);
  await freePort(frontendPort);
  const phpLog = openSync(join(local, `${logPrefix}-laravel.log`), "a");
  const viteLog = openSync(join(local, `${logPrefix}-vite.log`), "a");
  const api = child(
    php,
    [
      "-S",
      `localhost:${backendPort}`,
      "-t",
      ".",
      join(
        backend,
        "vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php",
      ),
    ],
    {
      cwd: join(backend, "public"),
      env,
      stdio: ["ignore", phpLog, phpLog],
    },
  );
  const app = child(
    node,
    [
      join(frontend, "node_modules/vite/bin/vite.js"),
      "--host",
      "localhost",
      "--port",
      String(frontendPort),
      "--strictPort",
    ],
    {
      cwd: frontend,
      env: {
        ...baseEnv,
        VITE_API_MODE: "real",
        VITE_API_BASE_URL: `http://localhost:${backendPort}`,
      },
      stdio: ["ignore", viteLog, viteLog],
    },
  );
  await Promise.all([
    ready(`http://localhost:${backendPort}/api/v1/health`, api),
    ready(`http://localhost:${frontendPort}`, app),
  ]);
  return [api, app];
}
function npmCli() {
  const candidates = [join(dirname(node), "node_modules/npm/bin/npm-cli.js")];
  for (const dir of (process.env.PATH || "").split(delimiter))
    candidates.push(join(dir, "node_modules/npm/bin/npm-cli.js"));
  const found = candidates.find(existsSync);
  if (!found)
    throw new Error("No se encontró npm. Instala npm 10+ junto a Node.");
  return found;
}
async function setup() {
  if (!process.argv.includes("--skip-install")) {
    await run("composer", ["install", "--no-interaction", "--prefer-dist"], {
      cwd: backend,
      shell: process.platform === "win32",
    });
    await run(node, [npmCli(), "install"], { cwd: frontend });
  }
  const envPath = join(backend, ".env");
  if (!existsSync(envPath)) {
    let contents = readFileSync(join(backend, ".env.example"), "utf8");
    for (const [key, value] of Object.entries({
      APP_KEY: `base64:${randomBytes(32).toString("base64")}`,
      APP_DEBUG: "false",
      DEMO_NURSE_EMAIL: "enfermera@kaycare.test",
      DEMO_NURSE_PASSWORD: randomBytes(24).toString("hex"),
    }))
      contents = setEnv(contents, key, value);
    writeFileSync(envPath, contents, { ...utf8, flag: "wx" });
  }
  const env = localEnv();
  if (!env.APP_KEY)
    throw new Error(
      "APP_KEY está vacío. Ejecuta php artisan key:generate una sola vez; no se cambió una clave existente.",
    );
  const isNew = !existsSync(env.DB_DATABASE);
  if (isNew) {
    mkdirSync(dirname(env.DB_DATABASE), { recursive: true });
    writeFileSync(env.DB_DATABASE, "", { flag: "wx" });
  }
  await run(php, ["artisan", "migrate", "--no-interaction"], {
    cwd: backend,
    env,
  });
  if (isNew)
    await run(php, ["artisan", "db:seed", "--no-interaction"], {
      cwd: backend,
      env,
    });
  else
    console.log(
      "Base de datos existente: no se ejecutaron seeders ni se sobrescribieron sus registros.",
    );
  const frontendEnv = join(frontend, ".env.local");
  if (!existsSync(frontendEnv))
    writeFileSync(
      frontendEnv,
      "VITE_API_MODE=real\nVITE_API_BASE_URL=http://localhost:8000\n",
      { ...utf8, flag: "wx" },
    );
  console.log(
    "Kay Care preparado. Ejecuta .\\scripts\\start.ps1. Cuenta ficticia: DEMO_NURSE_EMAIL y DEMO_NURSE_PASSWORD en backend/.env (no se imprimió la contraseña).",
  );
}
async function start() {
  const env = localEnv();
  if (!existsSync(env.DB_DATABASE))
    throw new Error("Ejecuta scripts/setup.ps1 antes de iniciar.");
  const services = await servers(
    8000,
    5173,
    {
      ...env,
      APP_URL: "http://localhost:8000",
      FRONTEND_URL: "http://localhost:5173",
      SANCTUM_STATEFUL_DOMAINS: "localhost:5173,localhost:8000",
    },
    "local",
  );
  console.log(
    "Kay Care: http://localhost:5173 · API: http://localhost:8000/api/v1/health\nModo real conectado a Laravel DEMO. Ctrl+C detiene ambos servidores. Logs en .local/.",
  );
  await Promise.race(
    services.map(
      (process) =>
        new Promise((resolve, reject) => {
          process.once("exit", (code) =>
            code === 0
              ? resolve()
              : reject(
                  new Error("Uno de los servidores se detuvo. Revisa .local/."),
                ),
          );
          process.once("error", reject);
        }),
    ),
  );
}
async function integration() {
  const runId = `integration-${randomUUID()}`;
  const database = join(local, `${runId}.sqlite`);
  writeFileSync(database, "", { flag: "wx" });
  const email = "integracion@kaycare.test";
  const password = randomBytes(24).toString("hex");
  const env = {
    ...baseEnv,
    APP_ENV: "local",
    APP_KEY: `base64:${randomBytes(32).toString("base64")}`,
    APP_DEBUG: "false",
    KAYCARE_MODE: "demo",
    KAYCARE_TIMEZONE: "America/Lima",
    DB_CONNECTION: "sqlite",
    DB_DATABASE: database,
    DB_URL: "",
    APP_CONFIG_CACHE: join(local, `${runId}-config-unused.php`),
    APP_URL: "http://localhost:8001",
    FRONTEND_URL: "http://localhost:5175",
    SANCTUM_STATEFUL_DOMAINS: "localhost:5175,localhost:8001",
    SESSION_DRIVER: "database",
    SESSION_COOKIE: "kaycare_integration_session",
    SESSION_DOMAIN: "null",
    SESSION_SECURE_COOKIE: "false",
    SESSION_ENCRYPT: "true",
    CACHE_STORE: "array",
    DEMO_NURSE_EMAIL: email,
    DEMO_NURSE_PASSWORD: password,
    DEMO_ADMIN_EMAIL: "",
    DEMO_ADMIN_PASSWORD: "",
    BCRYPT_ROUNDS: "4",
  };
  await run(php, ["artisan", "migrate", "--seed", "--no-interaction"], {
    cwd: backend,
    env,
  });
  await servers(8001, 5175, env, runId);
  await run(
    node,
    [
      join(frontend, "node_modules/@playwright/test/cli.js"),
      "test",
      "--config",
      "playwright.integration.config.ts",
    ],
    {
      cwd: frontend,
      env: {
        ...baseEnv,
        KAYCARE_TEST_EMAIL: email,
        KAYCARE_TEST_PASSWORD: password,
        KAYCARE_TEST_DATABASE: database,
      },
    },
  );
  console.log(
    `Integración real aprobada. Base SQLite ficticia retenida para inspección: ${database}`,
  );
}
try {
  const action = process.argv[2];
  if (action === "setup") await setup();
  else if (action === "start") await start();
  else if (action === "test") await integration();
  else throw new Error("Uso: node scripts/local.mjs setup | start | test");
} catch (error) {
  console.error(
    error instanceof Error
      ? error.message
      : "No se pudo completar la operación local.",
  );
  process.exitCode = 1;
} finally {
  stop();
}
