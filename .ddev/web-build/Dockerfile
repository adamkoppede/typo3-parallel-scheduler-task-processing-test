# We cannot use multi-stage docker builds because DDEV doesn't let us inject
# Dockerfile instructions above the beginning of the final build stage.
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs \
    | sh -s -- --profile minimal --default-toolchain stable -y

WORKDIR /reproduction-helper

COPY ["./reproduction-helper/Cargo.toml", "./reproduction-helper/Cargo.lock", "/reproduction-helper/"]
COPY ["./reproduction-helper/src/", "/reproduction-helper/src/"]

RUN . ~/.cargo/env \
    && cargo build --release \
    && mv /reproduction-helper/target/release/reproduction-helper /usr/bin/
