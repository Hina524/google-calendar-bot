# NixOS module for Google Calendar Bot
# Import this module via flake input
{ self }:
{ config, lib, pkgs, ... }:

with lib;

let
  cfg = config.services.calendar-bot;
  imageTag = self.shortRev or self.dirtyShortRev or "dev";
in
{
  options.services.calendar-bot = {
    enable = mkEnableOption "Google Calendar Bot";

    port = mkOption {
      type = types.port;
      default = 8080;
      description = "Port to expose the service on";
    };

    dataDir = mkOption {
      type = types.path;
      default = "/var/lib/calendar-bot";
      description = "Directory for persistent data";
    };

    environmentFile = mkOption {
      type = types.nullOr types.path;
      default = null;
      description = "Environment file containing secrets (from SOPS)";
    };
  };

  config = mkIf cfg.enable {
    # Ensure Docker is enabled
    virtualisation.docker.enable = true;

    # Ensure data directory exists (82:82 = www-data in Alpine)
    systemd.tmpfiles.rules = [
      "d ${cfg.dataDir} 0755 82 82 -"
      "d ${cfg.dataDir}/database 0755 82 82 -"
      "d ${cfg.dataDir}/storage 0755 82 82 -"
    ];

    # Build Docker image during nixos-rebuild
    system.activationScripts.calendar-bot-build = ''
      if ! ${pkgs.docker}/bin/docker image inspect calendar-bot:${imageTag} >/dev/null 2>&1; then
        echo "Building calendar-bot:${imageTag}..."
        ${pkgs.docker}/bin/docker build -t calendar-bot:${imageTag} -t calendar-bot:latest ${self}
      fi
    '';

    # Docker container as systemd service
    virtualisation.oci-containers.containers.calendar-bot = {
      image = "calendar-bot:latest";
      ports = [ "${toString cfg.port}:80" ];
      volumes = [
        "${cfg.dataDir}/database:/var/www/html/database"
        "${cfg.dataDir}/storage:/var/www/html/storage"
      ];
      environmentFiles = lib.optional (cfg.environmentFile != null) cfg.environmentFile;
      extraOptions = [
        "--health-cmd=curl -f http://localhost/up || exit 1"
        "--health-interval=30s"
        "--health-timeout=10s"
        "--health-retries=3"
      ];
    };
  };
}
