FROM golang:1.24-alpine AS build

# Official RELEASE.2025-10-15T17-29-55Z, including the STS policy security fix.
# MinIO no longer distributes public container images; build its pinned source.
ENV CGO_ENABLED=0
RUN go install github.com/minio/minio@9e49d5e7a648f00e26f2246f4dc28e6b07f8c84a

FROM alpine:3.22
RUN apk add --no-cache ca-certificates
COPY --from=build /go/bin/minio /usr/local/bin/minio
ENTRYPOINT ["/usr/local/bin/minio"]
